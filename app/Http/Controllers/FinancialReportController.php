<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $start = $data['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $data['end_date'] ?? now()->toDateString();

        $base = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_entries.status', 'posted');

        $trialBalance = (clone $base)
            ->whereDate('journal_entries.entry_date', '<=', $end)
            ->select(
                'ledger_accounts.account_code',
                'ledger_accounts.name',
                'ledger_accounts.account_type',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('ledger_accounts.id', 'ledger_accounts.account_code', 'ledger_accounts.name', 'ledger_accounts.account_type')
            ->orderBy('ledger_accounts.account_code')
            ->get()
            ->map(function ($row) {
                $row->balance = round((float) $row->total_debit - (float) $row->total_credit, 2);
                return $row;
            });

        $profitLoss = (clone $base)
            ->whereBetween('journal_entries.entry_date', [$start, $end])
            ->whereIn('ledger_accounts.account_type', ['income', 'expense'])
            ->select(
                'ledger_accounts.account_code',
                'ledger_accounts.name',
                'ledger_accounts.account_type',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('ledger_accounts.id', 'ledger_accounts.account_code', 'ledger_accounts.name', 'ledger_accounts.account_type')
            ->orderBy('ledger_accounts.account_code')
            ->get()
            ->map(function ($row) {
                $row->balance = $row->account_type === 'income'
                    ? round((float) $row->total_credit - (float) $row->total_debit, 2)
                    : round((float) $row->total_debit - (float) $row->total_credit, 2);
                return $row;
            });

        $income = round((float) $profitLoss->where('account_type', 'income')->sum('balance'), 2);
        $expenses = round((float) $profitLoss->where('account_type', 'expense')->sum('balance'), 2);
        $netProfit = round($income - $expenses, 2);

        $cumulativeProfitLoss = (clone $base)
            ->whereDate('journal_entries.entry_date', '<=', $end)
            ->whereIn('ledger_accounts.account_type', ['income', 'expense'])
            ->select(
                'ledger_accounts.account_type',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit')
            )
            ->groupBy('ledger_accounts.account_type')
            ->get();
        $accumulatedIncome = (float) ($cumulativeProfitLoss->firstWhere('account_type', 'income')->total_credit ?? 0)
            - (float) ($cumulativeProfitLoss->firstWhere('account_type', 'income')->total_debit ?? 0);
        $accumulatedExpenses = (float) ($cumulativeProfitLoss->firstWhere('account_type', 'expense')->total_debit ?? 0)
            - (float) ($cumulativeProfitLoss->firstWhere('account_type', 'expense')->total_credit ?? 0);
        $accumulatedEarnings = round($accumulatedIncome - $accumulatedExpenses, 2);

        $balanceSheet = $trialBalance->filter(fn ($row) => in_array($row->account_type, ['asset', 'liability', 'equity']))
            ->values();
        $assets = round((float) $balanceSheet->where('account_type', 'asset')->sum(fn ($row) => $row->balance), 2);
        $liabilities = round((float) $balanceSheet->where('account_type', 'liability')->sum(fn ($row) => -$row->balance), 2);
        $equity = round((float) $balanceSheet->where('account_type', 'equity')->sum(fn ($row) => -$row->balance), 2);
        $equityIncludingEarnings = round($equity + $accumulatedEarnings, 2);
        $balanceCheck = round($assets - ($liabilities + $equityIncludingEarnings), 2);

        return view('accounting.reports.index', compact(
            'start', 'end', 'trialBalance', 'profitLoss', 'income',
            'expenses', 'netProfit', 'balanceSheet', 'assets',
            'liabilities', 'equity', 'accumulatedEarnings', 'equityIncludingEarnings', 'balanceCheck'
        ));
    }
}
