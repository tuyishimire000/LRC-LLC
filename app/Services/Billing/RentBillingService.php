<?php

namespace App\Services\Billing;

use App\Models\Lease;
use App\Models\RentCharge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RentBillingService
{
    public function generateForMonth(CarbonImmutable $period, ?int $userId = null): int
    {
        $monthStart = $period->startOfMonth();
        $monthEnd = $period->endOfMonth();
        $created = 0;

        Lease::query()
            ->with('terms')
            ->whereIn('status', ['approved', 'active'])
            ->whereDate('starts_on', '<=', $monthEnd)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $monthStart))
            ->orderBy('id')
            ->chunkById(100, function ($leases) use ($monthStart, $monthEnd, $userId, &$created) {
                foreach ($leases as $lease) {
                    $periodStart = $monthStart->max(CarbonImmutable::parse($lease->starts_on));
                    $leaseEnd = $lease->terminated_on
                        ? CarbonImmutable::parse($lease->terminated_on)
                        : ($lease->ends_on ? CarbonImmutable::parse($lease->ends_on) : $monthEnd);
                    $periodEnd = $monthEnd->min($leaseEnd);
                    if ($periodEnd->lt($periodStart)) {
                        continue;
                    }

                    // Prorate by occupied days. Round once at the end to avoid a cent-rounding error per day.
                    $amountCents = 0.0;
                    for ($day = $periodStart; $day->lte($periodEnd); $day = $day->addDay()) {
                        $term = $lease->terms
                            ->filter(fn ($item) => $item->effective_from->lte($day)
                                && (!$item->effective_to || $item->effective_to->gte($day)))
                            ->sortByDesc('effective_from')
                            ->first();
                        $monthlyRentCents = (int) round((float) ($term?->monthly_rent ?? $lease->monthly_rent) * 100);
                        $amountCents += $monthlyRentCents / $day->daysInMonth;
                    }

                    $amountCents = (int) round($amountCents);
                    if ($amountCents <= 0) {
                        continue;
                    }

                    $dueDay = min((int) $lease->rent_due_day, $monthStart->daysInMonth);
                    $dueDate = $monthStart->setDay($dueDay);
                    if ($dueDate->lt($periodStart)) {
                        $dueDate = $periodStart;
                    }

                    DB::transaction(function () use ($lease, $monthStart, $dueDate, $amountCents, $userId, &$created) {
                        $charge = RentCharge::query()->firstOrCreate(
                            ['lease_id' => $lease->id, 'billing_period' => $monthStart->toDateString()],
                            [
                                'charge_number' => 'RC-'.Str::upper(Str::random(12)),
                                'charged_on' => now()->toDateString(),
                                'due_on' => $dueDate->toDateString(),
                                'amount' => number_format($amountCents / 100, 2, '.', ''),
                                'currency' => 'RWF',
                                'status' => 'open',
                                'description' => 'Rent for '.$monthStart->format('F Y'),
                                'generated_by' => $userId,
                            ]
                        );

                        if ($charge->wasRecentlyCreated) {
                            $created++;
                        }
                    });
                }
            });

        return $created;
    }
}
