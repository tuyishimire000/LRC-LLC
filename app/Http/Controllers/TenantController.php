<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()->withCount('leases')->latest()->paginate(20);
        return view('tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_type' => ['required', 'in:individual,company'],
            'legal_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'company_registration' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $nationalId = $data['national_id'] ?? null;
        unset($data['national_id']);
        $data['national_id_encrypted'] = $nationalId;
        $data['tenant_code'] = 'TEN-'.Str::upper(Str::random(10));
        $data['status'] = 'active';

        $tenant = Tenant::create($data);

        return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant record created.');
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['leases.property', 'leases.unit']);
        return view('tenants.show', compact('tenant'));
    }
}
