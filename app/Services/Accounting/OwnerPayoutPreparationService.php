<?php

namespace App\Services\Accounting;

use App\Models\ManagementAgreement;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutItem;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyOwner;
use App\Models\RentPaymentAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerPayoutPreparationService
{
    /**
     * Prepare draft owner statements for a property and period.
     * No bank transfer is initiated by this service.
     */
    public function prepare(Property $property, CarbonImmutable $start, CarbonImmutable $end, ?int $userId = null): array
    {
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['period' => 'The period end must be on or after the start date.']);
        }
        if ($property->management_type !== 'client_managed') {
            throw ValidationException::withMessages(['property' => 'Owner payouts are only prepared for client-managed properties.']);
        }

        return DB::transaction(function () use ($property, $start, $end, $userId) {
            $agreement = ManagementAgreement::query()
                ->where('property_id', $property->id)
                ->where('status', 'active')
                ->whereDate('starts_on', '<=', $start->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $end->toDateString()))
                ->orderByDesc('starts_on')
                ->lockForUpdate()
                ->first();

            if (!$agreement) {
                throw ValidationException::withMessages(['agreement' => 'No active management agreement covers the complete payout period.']);
            }
            if ($agreement->fee_type === 'percentage' && ((float) $agreement->fee_value < 0 || (float) $agreement->fee_value > 100)) {
                throw ValidationException::withMessages(['agreement' => 'The management fee percentage must be between 0 and 100.']);
            }
            if (!in_array($agreement->fee_type, ['percentage', 'fixed'], true)) {
                throw ValidationException::withMessages(['agreement' => 'The management agreement has an unsupported fee type.']);
            }

            $ownership = PropertyOwner::query()
                ->where('property_id', $property->id)
                ->whereDate('effective_from', '<=', $start->toDateString())
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $end->toDateString()))
                ->with('owner')
                ->orderBy('owner_id')
                ->lockForUpdate()
                ->get();

            $share = round($ownership->sum(fn ($record) => (float) $record->ownership_percentage), 2);
            if ($ownership->isEmpty() || $share !== 100.0) {
                throw ValidationException::withMessages(['ownership' => 'Ownership shares covering the full payout period must total exactly 100%.']);
            }

            $collections = RentPaymentAllocation::query()
                ->whereHas('payment', fn ($q) => $q->where('status', 'verified')
                    ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()]))
                ->whereHas('charge.lease', fn ($q) => $q->where('property_id', $property->id))
                ->with('payment', 'charge.lease')
                ->lockForUpdate()
                ->get();

            $grossCents = $collections->sum(fn ($allocation) => (int) round((float) $allocation->amount * 100));
            $expenses = PropertyExpense::query()
                ->where('property_id', $property->id)
                ->where('status', 'approved')
                ->where('charge_to_owner', true)
                ->where('currency', 'RWF')
                ->whereBetween('incurred_on', [$start->toDateString(), $end->toDateString()])
                ->lockForUpdate()
                ->get();
            $expenseCents = $expenses->sum(fn ($expense) => (int) round((float) $expense->amount * 100));

            $feeCents = $agreement->fee_type === 'percentage'
                ? (int) round($grossCents * (float) $agreement->fee_value / 100)
                : (int) round((float) $agreement->fee_value * 100);

            $prepared = [];
            $allocatedGross = $allocatedFee = $allocatedExpenses = 0;
            $lastIndex = $ownership->count() - 1;
            foreach ($ownership->values() as $index => $record) {
                $percentage = (float) $record->ownership_percentage;
                // Allocate rounding remainders to the final owner so statement totals reconcile exactly.
                $ownerGross = $index === $lastIndex ? $grossCents - $allocatedGross : (int) round($grossCents * $percentage / 100);
                $ownerFee = $index === $lastIndex ? $feeCents - $allocatedFee : (int) round($feeCents * $percentage / 100);
                $ownerExpenses = $index === $lastIndex ? $expenseCents - $allocatedExpenses : (int) round($expenseCents * $percentage / 100);
                $allocatedGross += $ownerGross;
                $allocatedFee += $ownerFee;
                $allocatedExpenses += $ownerExpenses;

                $overlap = OwnerPayout::query()
                    ->where('owner_id', $record->owner_id)
                    ->where('property_id', $property->id)
                    ->whereDate('period_start', '<=', $end->toDateString())
                    ->whereDate('period_end', '>=', $start->toDateString())
                    ->where(function ($query) use ($start, $end) {
                        $query->whereDate('period_start', '<>', $start->toDateString())
                            ->orWhereDate('period_end', '<>', $end->toDateString());
                    })
                    ->lockForUpdate()
                    ->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['period' => 'An owner already has a payout statement overlapping this period. Resolve the existing draft or paid statement before preparing another period.']);
                }
                $net = $ownerGross - $ownerFee - $ownerExpenses;

                if ($net < 0) {
                    throw ValidationException::withMessages([
                        'payout' => 'Deductions exceed collections for '.$record->owner->legal_name.'. Record the approved expense or carry-forward policy before preparing payouts.',
                    ]);
                }

                $payout = OwnerPayout::query()->firstOrCreate([
                    'owner_id' => $record->owner_id,
                    'property_id' => $property->id,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ], [
                    'payout_number' => 'OP-'.Str::upper(Str::random(12)),
                    'gross_collected' => number_format($ownerGross / 100, 2, '.', ''),
                    'management_fee' => number_format($ownerFee / 100, 2, '.', ''),
                    'owner_expenses' => number_format($ownerExpenses / 100, 2, '.', ''),
                    'net_amount' => number_format($net / 100, 2, '.', ''),
                    'currency' => 'RWF',
                    'status' => 'draft',
                    'prepared_by' => $userId,
                ]);

                if ($payout->wasRecentlyCreated) {
                    if ($ownerGross > 0) {
                        OwnerPayoutItem::create([
                            'owner_payout_id' => $payout->id,
                            'item_type' => 'rent_collection',
                            'description' => 'Verified rent collections, ownership share '.$percentage.'%',
                            'amount' => number_format($ownerGross / 100, 2, '.', ''),
                        ]);
                    }
                    if ($ownerFee > 0) {
                        OwnerPayoutItem::create([
                            'owner_payout_id' => $payout->id,
                            'item_type' => 'management_fee',
                            'description' => 'Management fee allocated by ownership share',
                            'amount' => number_format(-$ownerFee / 100, 2, '.', ''),
                        ]);
                    }
                    if ($ownerExpenses > 0) {
                        OwnerPayoutItem::create([
                            'owner_payout_id' => $payout->id,
                            'item_type' => 'owner_expense',
                            'description' => 'Approved owner-borne property expenses allocated by ownership share',
                            'amount' => number_format(-$ownerExpenses / 100, 2, '.', ''),
                        ]);
                    }
                }
                $prepared[] = $payout->load('owner', 'property', 'items');
            }

            return $prepared;
        });
    }
}
