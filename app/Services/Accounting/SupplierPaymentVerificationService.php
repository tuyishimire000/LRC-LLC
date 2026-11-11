<?php

namespace App\Services\Accounting;

use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentVerificationService
{
    public function verify(SupplierPayment $payment, User $verifier, FinancialEventPostingService $posting, ?string $note = null): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $verifier, $posting, $note) {
            $locked = SupplierPayment::query()->whereKey($payment->id)->lockForUpdate()
                ->with('allocations.expense')->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Only pending supplier payments can be verified.']);
            }
            if ((int) $locked->recorded_by === (int) $verifier->id) {
                throw ValidationException::withMessages(['payment' => 'The person who recorded a supplier payment cannot verify it.']);
            }
            if ($locked->allocations->isEmpty()) {
                throw ValidationException::withMessages(['allocations' => 'Allocate this payment to approved expenses before verification.']);
            }

            $totalCents = 0;
            $expenseAllocations = $locked->allocations->groupBy('property_expense_id')->sortKeys();
            foreach ($expenseAllocations as $expenseId => $allocations) {
                $expense = \App\Models\PropertyExpense::query()->whereKey($expenseId)->lockForUpdate()->firstOrFail();
                if ($expense->status !== 'approved') {
                    throw ValidationException::withMessages(['allocations' => 'Only approved expenses can be settled.']);
                }
                if ($expense->currency !== $locked->currency) {
                    throw ValidationException::withMessages(['allocations' => 'Supplier payment and expense currencies do not match.']);
                }

                $amountCents = $allocations->sum(fn ($allocation) => (int) round((float) $allocation->amount * 100));
                if ($amountCents <= 0) {
                    throw ValidationException::withMessages(['allocations' => 'Every settlement allocation must be greater than zero.']);
                }

                $settledCents = (int) round((float) $expense->supplierAllocations()
                    ->whereHas('payment', fn ($query) => $query->where('status', 'verified'))
                    ->sum('amount') * 100);
                $expenseCents = (int) round((float) $expense->amount * 100);
                if ($settledCents + $amountCents > $expenseCents) {
                    throw ValidationException::withMessages(['allocations' => 'A supplier settlement exceeds the remaining payable amount of an expense.']);
                }
                $totalCents += $amountCents;
            }

            if ($totalCents !== (int) round((float) $locked->amount * 100)) {
                throw ValidationException::withMessages(['allocations' => 'Expense allocations must equal the full supplier payment amount.']);
            }

            $locked->update([
                'status' => 'verified',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'verification_note' => $note,
            ]);
            $posting->postVerifiedSupplierPayment($locked->fresh());
            return $locked->fresh(['allocations.expense', 'recorder', 'verifier']);
        });
    }
}
