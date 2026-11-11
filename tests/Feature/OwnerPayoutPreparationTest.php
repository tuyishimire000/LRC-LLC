<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\ManagementAgreement;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyOwner;
use App\Models\PropertyUnit;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\RentPaymentAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounting\OwnerPayoutPreparationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OwnerPayoutPreparationTest extends TestCase
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
    public function it_splits_verified_collections_fees_and_approved_expenses_by_ownership(): void
    {
        $property = Property::query()->create([
            'property_code' => 'PAYOUT-PROP',
            'management_type' => 'client_managed',
            'name' => 'Payout test property',
            'country' => 'RW',
            'status' => 'active',
        ]);
        $ownerA = Owner::query()->create(['owner_code' => 'OWN-A', 'owner_type' => 'individual', 'legal_name' => 'Owner A', 'status' => 'active']);
        $ownerB = Owner::query()->create(['owner_code' => 'OWN-B', 'owner_type' => 'individual', 'legal_name' => 'Owner B', 'status' => 'active']);
        PropertyOwner::query()->create(['property_id' => $property->id, 'owner_id' => $ownerA->id, 'ownership_percentage' => '60.00', 'effective_from' => '2026-01-01']);
        PropertyOwner::query()->create(['property_id' => $property->id, 'owner_id' => $ownerB->id, 'ownership_percentage' => '40.00', 'effective_from' => '2026-01-01']);

        $agreement = ManagementAgreement::query()->create([
            'agreement_number' => 'AGR-PAYOUT-1',
            'property_id' => $property->id,
            'starts_on' => '2026-01-01',
            'fee_type' => 'percentage',
            'fee_value' => '10.00',
            'payout_frequency' => 'monthly',
            'status' => 'active',
        ]);

        $unit = PropertyUnit::query()->create(['property_id' => $property->id, 'unit_code' => 'U-01', 'status' => 'occupied']);
        $tenant = Tenant::query()->create([
            'tenant_code' => 'TEN-PAYOUT',
            'tenant_type' => 'individual',
            'legal_name' => 'Payout tenant',
            'phone' => '0780000011',
            'status' => 'active',
        ]);
        $lease = Lease::query()->create([
            'lease_number' => 'LEASE-PAYOUT',
            'property_id' => $property->id,
            'property_unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'rent_due_day' => 1,
            'monthly_rent' => '100000.00',
            'security_deposit_required' => '0.00',
            'security_deposit_paid' => '0.00',
            'currency' => 'RWF',
            'billing_frequency' => 'monthly',
            'status' => 'active',
        ]);
        $charge = RentCharge::query()->create([
            'charge_number' => 'CHG-PAYOUT',
            'lease_id' => $lease->id,
            'billing_period' => '2026-10-01',
            'charged_on' => '2026-10-01',
            'due_on' => '2026-10-01',
            'amount' => '100000.00',
            'currency' => 'RWF',
            'status' => 'open',
        ]);
        $recorder = $this->staff('payout-recorder@example.test');
        $verifier = $this->staff('payout-verifier@example.test');
        $preparer = $this->staff('payout-preparer@example.test');
        $payment = RentPayment::query()->create([
            'payment_number' => 'PAY-PAYOUT',
            'tenant_id' => $tenant->id,
            'amount' => '100000.00',
            'currency' => 'RWF',
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-10-15',
            'status' => 'verified',
            'recorded_by' => $recorder->id,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ]);
        RentPaymentAllocation::query()->create([
            'rent_payment_id' => $payment->id,
            'rent_charge_id' => $charge->id,
            'amount' => '100000.00',
        ]);
        PropertyExpense::query()->create([
            'expense_number' => 'EXP-PAYOUT',
            'property_id' => $property->id,
            'management_agreement_id' => $agreement->id,
            'category' => 'repairs',
            'description' => 'Approved repair',
            'incurred_on' => '2026-10-20',
            'amount' => '10000.00',
            'currency' => 'RWF',
            'charge_to_owner' => true,
            'status' => 'approved',
            'recorded_by' => $recorder->id,
            'approved_by' => $verifier->id,
            'approved_at' => now(),
        ]);

        $service = app(OwnerPayoutPreparationService::class);
        $periodStart = CarbonImmutable::parse('2026-10-01');
        $periodEnd = CarbonImmutable::parse('2026-10-31');
        $payouts = $service->prepare($property, $periodStart, $periodEnd, $preparer->id);

        $this->assertCount(2, $payouts);
        $this->assertSame('48000.00', $payouts[0]->net_amount);
        $this->assertSame('32000.00', $payouts[1]->net_amount);
        $this->assertSame('draft', $payouts[0]->status);
        $this->assertSame('draft', $payouts[1]->status);

        $again = $service->prepare($property, $periodStart, $periodEnd, $preparer->id);
        $this->assertCount(2, $again);
        $this->assertDatabaseCount('owner_payouts', 2);
    }

    #[Test]
    public function it_rejects_owner_shares_that_do_not_total_one_hundred_percent(): void
    {
        $property = Property::query()->create([
            'property_code' => 'PAYOUT-BAD',
            'management_type' => 'client_managed',
            'name' => 'Invalid ownership property',
            'country' => 'RW',
            'status' => 'active',
        ]);
        $owner = Owner::query()->create(['owner_code' => 'OWN-BAD', 'owner_type' => 'individual', 'legal_name' => 'Owner Bad', 'status' => 'active']);
        PropertyOwner::query()->create(['property_id' => $property->id, 'owner_id' => $owner->id, 'ownership_percentage' => '90.00', 'effective_from' => '2026-01-01']);
        ManagementAgreement::query()->create([
            'agreement_number' => 'AGR-PAYOUT-BAD',
            'property_id' => $property->id,
            'starts_on' => '2026-01-01',
            'fee_type' => 'percentage',
            'fee_value' => '10.00',
            'payout_frequency' => 'monthly',
            'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);
        app(OwnerPayoutPreparationService::class)->prepare(
            $property,
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31')
        );
    }
}
