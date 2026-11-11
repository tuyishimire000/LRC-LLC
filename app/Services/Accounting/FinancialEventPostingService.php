<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\ManagementAgreement;
use App\Models\OwnerPayout;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyOwner;
use App\Models\RentPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialEventPostingService
{
    public function __construct(private readonly JournalPostingService $journals) {}

    public function postVerifiedRentPayment(RentPayment $payment): ?JournalEntry
    {
        return DB::transaction(function () use ($payment) {
            $payment = RentPayment::query()->whereKey($payment->id)->lockForUpdate()
                ->with('allocations.charge.lease.property')->firstOrFail();
            if ($payment->status !== 'verified') {
                throw ValidationException::withMessages(['payment' => 'Only verified payments can be posted.']);
            }
            if ($existing = $this->existing('rent_payment', $payment->id)) return $existing;

            $groups = $payment->allocations->groupBy(fn ($a) => $a->charge->lease->property_id);
            if ($groups->isEmpty()) throw ValidationException::withMessages(['payment' => 'Payment has no property-linked allocations.']);

            $lines = [];
            $totalCents = 0;
            foreach ($groups as $allocations) {
                $property = $allocations->first()->charge->lease->property;
                $gross = $allocations->sum(fn ($a) => $this->cents($a->amount));
                $totalCents += $gross;
                if ($property->management_type === 'lrc_owned') {
                    $lines[] = $this->line($this->account('4100'), 0, $gross, $property->id, null, 'LRC-owned rental income');
                    continue;
                }
                if ($property->management_type !== 'client_managed') {
                    throw ValidationException::withMessages(['property' => 'Unsupported property management type.']);
                }

                $date = $payment->paid_on->toDateString();
                $agreements = ManagementAgreement::query()->where('property_id', $property->id)
                    ->where('status', 'active')->whereDate('starts_on', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
                    ->lockForUpdate()->get();
                if ($agreements->count() !== 1) {
                    throw ValidationException::withMessages(['agreement' => 'Exactly one active management agreement must cover '.$property->name.' on '.$date.'.']);
                }
                $agreement = $agreements->first();
                if (!in_array($agreement->fee_type, ['percentage', 'fixed'], true)) {
                    throw ValidationException::withMessages(['agreement' => 'Unsupported management fee type.']);
                }

                $owners = PropertyOwner::query()->where('property_id', $property->id)
                    ->whereDate('effective_from', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
                    ->orderBy('owner_id')->lockForUpdate()->get();
                $share = round($owners->sum(fn ($o) => (float) $o->ownership_percentage), 2);
                if ($owners->isEmpty() || $owners->pluck('owner_id')->unique()->count() !== $owners->count() || $share !== 100.0) {
                    throw ValidationException::withMessages(['ownership' => 'Effective ownership must be unique and total 100% on '.$date.'.']);
                }

                $fee = $agreement->fee_type === 'percentage' ? (int) round($gross * (float) $agreement->fee_value / 100) : 0;
                if ($fee < 0 || $fee > $gross) throw ValidationException::withMessages(['agreement' => 'Management fee cannot exceed collected rent.']);
                $ownerTotal = $gross - $fee;
                $allocated = 0;
                foreach ($owners->values() as $index => $owner) {
                    $part = $index === $owners->count() - 1
                        ? $ownerTotal - $allocated
                        : (int) round($ownerTotal * (float) $owner->ownership_percentage / 100);
                    $allocated += $part;
                    if ($part > 0) $lines[] = $this->line($this->account('2000'), 0, $part, $property->id, $owner->owner_id, 'Collected rent payable to owner');
                }
                if ($fee > 0) $lines[] = $this->line($this->account('4000'), 0, $fee, $property->id, null, 'Contractual percentage management fee');
            }

            if ($totalCents !== $this->cents($payment->amount)) {
                throw ValidationException::withMessages(['payment' => 'Property allocation total differs from verified payment amount.']);
            }
            array_unshift($lines, $this->line($this->account('1000'), $totalCents, 0, null, null, 'Verified tenant rent receipt'));
            return $this->post('rent_payment', $payment->id, $payment->paid_on, 'Verified rent payment '.$payment->payment_number, $lines, $payment->recorded_by, $payment->verified_by);
        });
    }

    public function postApprovedOwnerExpense(PropertyExpense $expense): ?JournalEntry
    {
        return DB::transaction(function () use ($expense) {
            $expense = PropertyExpense::query()->whereKey($expense->id)->lockForUpdate()->with('property')->firstOrFail();
            if ($expense->status !== 'approved') throw ValidationException::withMessages(['expense' => 'Only approved expenses can be posted.']);
            if ($existing = $this->existing('property_expense', $expense->id)) return $existing;

            $amount = $this->cents($expense->amount);
            $lines = [];
            if ($expense->charge_to_owner && $expense->property->management_type === 'client_managed') {
                $date = $expense->incurred_on->toDateString();
                $owners = PropertyOwner::query()->where('property_id', $expense->property_id)
                    ->whereDate('effective_from', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
                    ->orderBy('owner_id')->lockForUpdate()->get();
                $share = round($owners->sum(fn ($o) => (float) $o->ownership_percentage), 2);
                if ($owners->isEmpty() || $owners->pluck('owner_id')->unique()->count() !== $owners->count() || $share !== 100.0) {
                    throw ValidationException::withMessages(['ownership' => 'Effective ownership must total 100% on the expense date.']);
                }
                $allocated = 0;
                foreach ($owners->values() as $index => $owner) {
                    $part = $index === $owners->count() - 1 ? $amount - $allocated : (int) round($amount * (float) $owner->ownership_percentage / 100);
                    $allocated += $part;
                    if ($part > 0) $lines[] = $this->line($this->account('2000'), $part, 0, $expense->property_id, $owner->owner_id, 'Approved owner-borne property expense');
                }
            } else {
                $lines[] = $this->line($this->account('5100'), $amount, 0, $expense->property_id, null, 'LRC-borne property expense');
            }
            $lines[] = $this->line($this->account('2200'), 0, $amount, $expense->property_id, null, 'Expense supplier payable');
            return $this->post('property_expense', $expense->id, $expense->incurred_on, 'Approved expense '.$expense->expense_number, $lines, $expense->recorded_by, $expense->approved_by);
        });
    }

    public function postFixedManagementFee(OwnerPayout $payout): ?JournalEntry
    {
        return DB::transaction(function () use ($payout) {
            $payout = OwnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'approved') throw ValidationException::withMessages(['payout' => 'Only approved statements can accrue fees.']);
            if ($existing = $this->existing('owner_payout_fee', $payout->id)) return $existing;
            $agreements = ManagementAgreement::query()->where('property_id', $payout->property_id)
                ->where('status', 'active')
                ->whereDate('starts_on', '<=', $payout->period_start->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $payout->period_end->toDateString()))
                ->lockForUpdate()->get();
            if ($agreements->count() !== 1) {
                throw ValidationException::withMessages(['agreement' => 'Exactly one active management agreement must cover this complete owner statement period.']);
            }
            // Percentage fees were already recognized when each rent payment was posted.
            // Only fixed fees are accrued at statement approval.
            if ($agreements->first()->fee_type !== 'fixed') return null;
            $amount = $this->cents($payout->management_fee);
            if ($amount <= 0) return null;
            $lines = [
                $this->line($this->account('2000'), $amount, 0, $payout->property_id, $payout->owner_id, 'Fixed management fee deducted from owner funds'),
                $this->line($this->account('4000'), 0, $amount, $payout->property_id, null, 'Fixed contractual management fee income'),
            ];
            return $this->post('owner_payout_fee', $payout->id, $payout->period_end, 'Fixed management fee '.$payout->payout_number, $lines, $payout->prepared_by, $payout->approved_by);
        });
    }

    public function postOwnerTransfer(OwnerPayout $payout): ?JournalEntry
    {
        return DB::transaction(function () use ($payout) {
            $payout = OwnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'paid') throw ValidationException::withMessages(['payout' => 'Only completed transfers can be posted.']);
            if ($existing = $this->existing('owner_payout_transfer', $payout->id)) return $existing;
            $amount = $this->cents($payout->net_amount);
            if ($amount <= 0) return null;
            $ownerPayable = $this->account('2000');
            $available = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_lines.ledger_account_id', $ownerPayable->id)
                ->where('journal_lines.property_id', $payout->property_id)
                ->where('journal_lines.owner_id', $payout->owner_id)
                ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) - COALESCE(SUM(journal_lines.debit), 0) as balance')
                ->value('balance');
            if ($this->cents($available ?? 0) < $amount) {
                throw ValidationException::withMessages(['payout' => 'The owner sub-ledger balance is insufficient for this payout. Reconcile collections, approved expenses, fees and previous transfers before recording it.']);
            }
            $lines = [
                $this->line($ownerPayable, $amount, 0, $payout->property_id, $payout->owner_id, 'Owner payout settles owner funds payable'),
                $this->line($this->account('1000'), 0, $amount, $payout->property_id, null, 'Owner payout from bank/payment clearing'),
            ];
            return $this->post('owner_payout_transfer', $payout->id, $payout->period_end, 'Owner payout '.$payout->payout_number, $lines, $payout->approved_by, $payout->paid_by);
        });
    }

    private function post(string $sourceType, int $sourceId, CarbonInterface $date, string $description, array $lines, ?int $creatorId, ?int $posterId): JournalEntry
    {
        if (!$creatorId || !$posterId || $creatorId === $posterId) {
            throw ValidationException::withMessages(['approval' => 'Financial posting requires independent maker and checker users.']);
        }
        $entry = $this->journals->createDraft([
            'entry_date' => $date->toDateString(), 'description' => $description,
            'source_type' => $sourceType, 'source_id' => $sourceId,
        ], $lines, $creatorId);
        return $this->journals->post($entry, User::query()->findOrFail($posterId));
    }

    private function existing(string $type, int $id): ?JournalEntry
    {
        return JournalEntry::query()->where('source_type', $type)->where('source_id', $id)->where('status', 'posted')->with('lines')->first();
    }

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::query()->where('account_code', $code)->where('is_active', true)->firstOrFail();
    }

    private function cents(mixed $amount): int { return (int) round((float) $amount * 100); }

    private function line(LedgerAccount $account, int $debit, int $credit, ?int $propertyId, ?int $ownerId, string $description): array
    {
        return [
            'ledger_account_id' => $account->id, 'debit' => number_format($debit / 100, 2, '.', ''),
            'credit' => number_format($credit / 100, 2, '.', ''), 'property_id' => $propertyId,
            'owner_id' => $ownerId, 'description' => $description,
        ];
    }
}
