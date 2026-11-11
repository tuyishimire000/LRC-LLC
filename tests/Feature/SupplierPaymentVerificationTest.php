<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\SupplierPayment;
use App\Services\Accounting\FinancialEventPostingService;
use App\Services\Accounting\SupplierPaymentVerificationService;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $email): User
    {
        return User::query()->create([
            'name' => 'Finance staff',
            'email' => $email,
            'password' => 'a-secure-test-password',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function verified_supplier_payment_reduces_vendor_payable_and_is_posted_once(): void
    {
        $this->artisan('lrc:accounting-seed');
        $property = Property::query()->create([
            'property_code' => 'SUPPLIER-PROP',
            'management_type' => 'client_managed',
            'name' => 'Supplier payment property',
            'country' => 'RW',
            'status' => 'active',
        ]);
        $recorder = $this->staff('supplier-expense-recorder@example.test');
        $approver = $this->staff('supplier-expense-approver@example.test');
        $paymentRecorder = $this->staff('supplier-payment-recorder@example.test');
        $verifier = $this->staff('supplier-payment-verifier@example.test');

        $expense = PropertyExpense::query()->create([
            'expense_number' => 'EXP-SUPPLIER-1',
            'property_id' => $property->id,
            'category' => 'repairs',
            'description' => 'Repair invoice',
            'incurred_on' => '2026-11-05',
            'amount' => '50000.00',
            'currency' => 'RWF',
            'charge_to_owner' => false,
            'status' => 'approved',
            'recorded_by' => $recorder->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $payment = SupplierPayment::query()->create([
            'payment_number' => 'SUP-PAY-1',
            'amount' => '30000.00',
            'currency' => 'RWF',
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-11-10',
            'status' => 'pending',
            'recorded_by' => $paymentRecorder->id,
        ]);
        SupplierPaymentAllocation::query()->create([
            'supplier_payment_id' => $payment->id,
            'property_expense_id' => $expense->id,
            'amount' => '30000.00',
        ]);

        $service = app(SupplierPaymentVerificationService::class);
        $posting = app(FinancialEventPostingService::class);
        $service->verify($payment, $verifier, $posting);
        $posting->postVerifiedSupplierPayment($payment->fresh());

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'supplier_payment',
            'source_id' => $payment->id,
            'status' => 'posted',
        ]);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame(30000.0, (float) \Illuminate\Support\Facades\DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.source_type', 'supplier_payment')
            ->where('journal_entries.source_id', $payment->id)
            ->where('journal_lines.ledger_account_id', \App\Models\LedgerAccount::query()->where('account_code', '2200')->value('id'))
            ->sum('debit'));
    }

    #[Test]
    public function supplier_payment_cannot_settle_more_than_the_expense_balance(): void
    {
        $this->artisan('lrc:accounting-seed');
        $property = Property::query()->create([
            'property_code' => 'SUPPLIER-PROP-2',
            'management_type' => 'client_managed',
            'name' => 'Supplier payment property 2',
            'country' => 'RW',
            'status' => 'active',
        ]);
        $recorder = $this->staff('supplier-expense-recorder-2@example.test');
        $approver = $this->staff('supplier-expense-approver-2@example.test');
        $paymentRecorder = $this->staff('supplier-payment-recorder-2@example.test');
        $verifier = $this->staff('supplier-payment-verifier-2@example.test');
        $expense = PropertyExpense::query()->create([
            'expense_number' => 'EXP-SUPPLIER-2',
            'property_id' => $property->id,
            'category' => 'repairs',
            'description' => 'Smaller repair invoice',
            'incurred_on' => '2026-11-05',
            'amount' => '10000.00',
            'currency' => 'RWF',
            'charge_to_owner' => false,
            'status' => 'approved',
            'recorded_by' => $recorder->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
        $payment = SupplierPayment::query()->create([
            'payment_number' => 'SUP-PAY-2',
            'amount' => '12000.00',
            'currency' => 'RWF',
            'payment_method' => 'cash',
            'paid_on' => '2026-11-10',
            'status' => 'pending',
            'recorded_by' => $paymentRecorder->id,
        ]);
        SupplierPaymentAllocation::query()->create([
            'supplier_payment_id' => $payment->id,
            'property_expense_id' => $expense->id,
            'amount' => '12000.00',
        ]);

        $this->expectException(ValidationException::class);
        app(SupplierPaymentVerificationService::class)->verify(
            $payment,
            $verifier,
            app(FinancialEventPostingService::class)
        );
    }
}
