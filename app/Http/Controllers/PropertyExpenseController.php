<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PropertyExpenseController extends Controller
{
    public function index(): View
    {
        $expenses = PropertyExpense::query()->with('property')->latest('incurred_on')->paginate(30);
        return view('accounting.expenses.index', compact('expenses'));
    }

    public function create(): View
    {
        return view('accounting.expenses.create', [
            'properties' => Property::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'charge_to_owner' => ['required', 'boolean'],
        ]);

        PropertyExpense::query()->create([
            ...$data,
            'expense_number' => 'EXP-'.Str::upper(Str::random(12)),
            'currency' => 'RWF',
            'status' => 'pending',
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('accounting.expenses.index')->with('success', 'Expense recorded as pending. It will not be deducted from an owner statement until independently approved.');
    }

    public function approve(Request $request, PropertyExpense $expense): RedirectResponse
    {
        DB::transaction(function () use ($request, $expense) {
            $locked = PropertyExpense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['expense' => 'Only pending expenses can be approved.']);
            }
            if ((int) $locked->recorded_by === (int) $request->user()->id) {
                throw ValidationException::withMessages(['expense' => 'The person who recorded an expense cannot approve it.']);
            }
            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_note' => $request->input('approval_note'),
            ]);
        });

        return redirect()->route('accounting.expenses.index')->with('success', 'Expense approved. Owner statements may include it if it is marked owner-borne.');
    }
}
