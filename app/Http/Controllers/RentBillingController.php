<?php

namespace App\Http\Controllers;

use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Services\Billing\RentPaymentVerificationService;
use App\Services\Accounting\FinancialEventPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RentBillingController extends Controller
{
    public function index(): View
    {
        $charges = RentCharge::query()
            ->with(['lease.tenant', 'lease.property', 'lease.unit', 'allocations'])
            ->orderByDesc('billing_period')->paginate(30);
        $payments = RentPayment::query()->with('tenant')->latest()->limit(20)->get();
        return view('billing.index', compact('charges', 'payments'));
    }

    public function createPayment(): View
    {
        return view('billing.payment-create', [
            'tenants' => Tenant::query()->where('status', 'active')->orderBy('legal_name')->get(),
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'payment_method' => ['required', 'in:cash,bank_transfer,momo,other'],
            'external_reference' => ['nullable', 'string', 'max:120', 'unique:rent_payments,external_reference'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $payment = RentPayment::query()->create([
            ...$data,
            'payment_number' => 'RP-'.Str::upper(Str::random(12)),
            'currency' => 'RWF',
            'status' => 'pending',
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('billing.index')->with('success', 'Payment recorded as pending. It does not reduce rent balances until verified and allocated.');
    }

    public function showVerificationForm(RentPayment $payment): View
    {
        abort_unless($payment->status === 'pending', 422, 'Only pending payments can be verified.');
        $charges = RentCharge::query()->with('allocations')->whereHas('lease', fn ($query) => $query->where('tenant_id', $payment->tenant_id))->where('currency', $payment->currency)->orderBy('due_on')->get()->filter(fn ($charge) => (float) $charge->outstanding_amount > 0);
        $payment->load('tenant');
        return view('billing.payment-verify', compact('payment', 'charges'));
    }

    public function verify(Request $request, RentPayment $payment, RentPaymentVerificationService $verification, FinancialEventPostingService $posting): RedirectResponse
    {
        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.rent_charge_id' => ['required', 'integer', 'distinct', 'exists:rent_charges,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'verification_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $allocations = array_values(array_filter($data['allocations'], fn ($item) => isset($item['amount']) && (float) $item['amount'] > 0));
        $verification->verify($payment, $allocations, $request->user(), $posting, $data['verification_note'] ?? null);
        return redirect()->route('billing.index')->with('success', 'Payment verified and allocated. Outstanding balances have been recalculated.');
    }
}