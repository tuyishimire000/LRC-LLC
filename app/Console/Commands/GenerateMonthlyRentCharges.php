<?php

namespace App\Console\Commands;

use App\Services\Billing\RentBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateMonthlyRentCharges extends Command
{
    protected $signature = 'lrc:rent-generate {--month= : Billing month in YYYY-MM format (defaults to current month)}';
    protected $description = 'Generate idempotent monthly rent charges for approved and active leases';

    public function handle(RentBillingService $billing): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        try {
            $period = CarbonImmutable::createFromFormat('!Y-m', $month);
            if (!$period || $period->format('Y-m') !== $month) throw new \InvalidArgumentException();
        } catch (\Throwable) {
            $this->error('Month must be a valid YYYY-MM value.');
            return self::FAILURE;
        }

        $count = $billing->generateForMonth($period);
        $this->info("Created {$count} rent charge(s) for {$period->format('F Y')}. Existing charges were left unchanged.");
        return self::SUCCESS;
    }
}