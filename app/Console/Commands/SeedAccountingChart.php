<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use Illuminate\Console\Command;

class SeedAccountingChart extends Command
{
    protected $signature = 'lrc:accounting-seed';
    protected $description = 'Create the provisional starter chart of accounts for LRC';

    public function handle(): int
    {
        $accounts = [
            ['1000', 'Bank / payment clearing', 'asset', 'debit', true],
            ['1100', 'Tenant rent receivable', 'asset', 'debit', true],
            ['2000', 'Client owner funds payable', 'liability', 'credit', true],
            ['2100', 'Tenant security deposits payable', 'liability', 'credit', true],
            ['3000', 'Opening balance / retained earnings', 'equity', 'credit', false],
            ['4000', 'Property management fee income', 'income', 'credit', false],
            ['5000', 'Owner-borne property expenses', 'expense', 'debit', false],
            ['5100', 'LRC operating expenses', 'expense', 'debit', false],
        ];

        foreach ($accounts as [$code, $name, $type, $normal, $control]) {
            LedgerAccount::query()->updateOrCreate(
                ['account_code' => $code],
                ['name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_control' => $control, 'is_active' => true]
            );
        }

        $this->info('Provisional starter chart created/updated ('.count($accounts).' accounts).');
        $this->warn('Have an accountant approve the chart and map payment, deposit, fee, expense and payout events before posting live transactions.');
        return self::SUCCESS;
    }
}
