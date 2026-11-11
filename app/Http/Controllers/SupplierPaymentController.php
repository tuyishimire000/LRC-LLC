<?php

namespace App\Http\Controllers;

use App\Models\PropertyExpense;
use App\Models\SupplierPayment;
use App\Services\Accounting\FinancialEventPostingService;
use App\Services\Accounting\SupplierPaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    public function index(): View
    {
        $payments = SupplierPayment::query()->with(['allocations.expense.property', 'recorder', 'verifier'])
            ->latest('paid_on')->paginate(30);
        return view('accounting.supplier-payments.index', compact('payments'));
    }

    public function create(): View
    {
        $expenses = PropertyExpense::query()->where('status', 'approved')
            ->with(['property', 'supplierAllocations.payment'])
            ->orderBy('incurred_on')->get()
            ->map(function ($expense) {
                $settled = $expense->supplierAllocations
                    ->filter(fn ($allocation) => $allocation->payment?->status === 'verified')
                    ->sum(fn ($allocation) => (float) $allocation->amount);
                $expense->outstanding_supplier_amount = number_format(max(0, (float) $expense->amount - $settled), 2, '.', '');
                return $expense;
            })
            ->filter(fn ($expense) => (float) $expense->outstanding_supplier_amount > 0)
            ->values();

        return view('accounting.supplier-payments.create', compact('expenses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'payment_method' => ['required', 'in:cash,bank_transfer,momo,other'],
            'external_reference' => ['nullable', 'string', 'max:120', 'unique:supplier_payments,external_reference'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.property_expense_id' => ['required', 'integer', 'distinct', 'exists:property_expenses,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
        ]);

        $data['allocations'] = array_values(array_filter($data['allocations'], fn ($item) => isset($item['amount']) && (float) $item['amount'] > 0));
        if ($data['allocations'] === []) {
            throw ValidationException::withMessages(['allocations' => 'Enter an amount against at least one approved expense.']);
        }
        $totalCents = array_sum(array_map(fn ($item) => (int) round((float) $item['amount'] * 100), $data['allocations']));
        if ($totalCents !== (int) round((float) $data['amount'] * 100)) {
            throw ValidationException::withMessages(['allocations' => 'Expense allocations must equal the supplier payment amount.']);
        }

        DB::transaction(function () use ($request, $data) {
            $payment = SupplierPayment::query()->create([
                'payment_number' => 'SP-'.Str::upper(Str::random(12)),
                'amount' => $data['amount'],
                'currency' => 'RWF',
                'payment_method' => $data['payment_method'],
                'external_reference' => $data['external_reference'] ?? null,
                'paid_on' => $data['paid_on'],
                'status' => 'pending',
                'recorded_by' => $request->user()->id,
            ]);
            foreach ($data['allocations'] as $allocation) {
                $payment->allocations()->create([
                    'property_expense_id' => $allocation['property_expense_id'],
                    'amount' => $allocation['amount'],
                ]);
            }
        });

        return redirect()->route('accounting.supplier-payments.index')
            ->with('success', 'Supplier payment recorded as pending. A different finance user must verify it before vendor payable is cleared.');
    }

    public function verify(Request $request, SupplierPayment $payment, SupplierPaymentVerificationService $verification, FinancialEventPostingService $posting): RedirectResponse
    {
        $data = $request->validate(['verification_note' => ['nullable', 'string', 'max:2000']]);
        $verification->verify($payment, $request->user(), $posting, $data['verification_note'] ?? null);

        return redirect()->route('accounting.supplier-payments.index')
            ->with('success', 'Supplier payment verified and vendor payable settled in the ledger.');
    }
}
