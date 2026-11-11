<?php

namespace App\Http\Controllers;

use App\Models\OwnerPayout;
use App\Models\Property;
use App\Services\Accounting\OwnerPayoutLifecycleService;
use App\Services\Accounting\OwnerPayoutPreparationService;
use App\Services\Accounting\FinancialEventPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerPayoutController extends Controller
{
    public function index(): View
    {
        $payouts = OwnerPayout::query()->with(['owner', 'property'])->latest('period_end')->paginate(30);
        return view('accounting.payouts.index', compact('payouts'));
    }

    public function create(): View
    {
        return view('accounting.payouts.create', [
            'properties' => Property::query()->where('management_type', 'client_managed')->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, OwnerPayoutPreparationService $preparation): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $property = Property::query()->findOrFail($data['property_id']);
        $payouts = $preparation->prepare(
            $property,
            CarbonImmutable::parse($data['period_start']),
            CarbonImmutable::parse($data['period_end']),
            $request->user()->id
        );

        return redirect()->route('accounting.payouts.index')->with('success', count($payouts).' draft owner statement(s) prepared. No transfers were initiated.');
    }

    public function approve(Request $request, OwnerPayout $payout, OwnerPayoutLifecycleService $lifecycle, FinancialEventPostingService $posting): RedirectResponse
    {
        $lifecycle->approve($payout, $request->user(), $posting, $request->validate([
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ])['approval_note'] ?? null);

        return redirect()->route('accounting.payouts.index')->with('success', 'Owner payout approved. A different finance operator must record the actual transfer.');
    }

    public function recordTransfer(Request $request, OwnerPayout $payout, OwnerPayoutLifecycleService $lifecycle, FinancialEventPostingService $posting): RedirectResponse
    {
        $data = $request->validate(['payout_reference' => ['required', 'string', 'max:120']]);
        $lifecycle->recordTransfer($payout, $request->user(), $posting, $data['payout_reference']);

        return redirect()->route('accounting.payouts.index')->with('success', 'Transfer reference recorded. Reconcile it against the bank/provider statement.');
    }
}
