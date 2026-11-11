<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Owner;
use App\Models\PropertyOwner;
use App\Models\ManagementAgreement;
use App\Models\PropertyUnit;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\RentPaymentVerificationService;
use App\Services\Accounting\FinancialEventPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RentPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function paymentFixture(): array
    {
        $property = Property::create([
            'property_code' => 'BILL-PROP',
            'management_type' => 'client_managed',
            'name' => 'Billing test property',
            'country' => 'RW',
            'status' => 'active',
        ]);
        $owner = Owner::create(['owner_code' => 'BILL-OWNER', 'owner_type' => 'individual', 'legal_name' => 'Billing owner', 'status' => 'active']);
        PropertyOwner::create(['property_id' => $property->id, 'owner_id' => $owner->id, 'ownership_percentage' => '100.00', 'effective_from' => '2026-01-01']);
        ManagementAgreement::create(['agreement_number' => 'BILL-AGREEMENT', 'property_id' => $property->id, 'starts_on' => '2026-01-01', 'fee_type' => 'percentage', 'fee_value' => '10.00', 'payout_frequency' => 'monthly', 'status' => 'active']);
        $this->artisan('lrc:accounting-seed');
        $unit = PropertyUnit::create([
            'property_id' => $property->id,
            'unit_code' => 'B-01',
            'status' => 'occupied',
        ]);
        $tenant = Tenant::create([
            'tenant_code' => 'BILL-TEN',
            'tenant_type' => 'individual',
            'legal_name' => 'Billing test tenant',
            'phone' => '0780000001',
            'status' => 'active',
        ]);
        $lease = Lease::create([
            'lease_number' => 'BILL-LEASE',
            'property_id' => $property->id,
            'property_unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'ends_on' => null,
            'rent_due_day' => 1,
            'monthly_rent' => '100000.00',
            'security_deposit_required' => '0.00',
            'security_deposit_paid' => '0.00',
            'currency' => 'RWF',
            'billing_frequency' => 'monthly',
            'status' => 'active',
        ]);
        $charge = RentCharge::create([
            'charge_number' => 'BILL-CHARGE',
            'lease_id' => $lease->id,
            'billing_period' => '2026-11-01',
            'charged_on' => '2026-11-01',
            'due_on' => '2026-11-01',
            'amount' => '100000.00',
            'currency' => 'RWF',
            'status' => 'open',
        ]);
        $recorder = User::create([
            'name' => 'Payment recorder',
            'email' => 'recorder@example.test',
            'password' => 'a-secure-test-password',
            'status' => 'active',
        ]);
        $verifier = User::create([
            'name' => 'Payment verifier',
            'email' => 'verifier@example.test',
            'password' => 'a-secure-test-password',
            'status' => 'active',
        ]);
        $payment = RentPayment::create([
            'payment_number' => 'BILL-PAYMENT',
            'tenant_id' => $tenant->id,
            'amount' => '40000.00',
            'currency' => 'RWF',
            'payment_method' => 'bank_transfer',
            'paid_on' => '2026-11-10',
            'status' => 'pending',
            'recorded_by' => $recorder->id,
        ]);

        return compact('tenant', 'lease', 'charge', 'recorder', 'verifier', 'payment');
    }

    #[Test]
    public function verified_payment_allocations_reduce_the_charge_balance(): void
    {
        $fixture = $this->paymentFixture();

        app(RentPaymentVerificationService::class)->verify(
            $fixture['payment'],
            [['rent_charge_id' => $fixture['charge']->id, 'amount' => '40000.00']],
            $fixture['verifier'],
            app(FinancialEventPostingService::class)
        );

        $fixture['charge']->refresh();
        $fixture['payment']->refresh();

        $this->assertSame('verified', $fixture['payment']->status);
        $this->assertSame('40000.00', $fixture['charge']->allocated_amount);
        $this->assertSame('60000.00', $fixture['charge']->outstanding_amount);
        $this->assertSame('partial', $fixture['charge']->payment_status);
        $this->assertDatabaseHas('journal_entries', ['source_type' => 'rent_payment', 'source_id' => $fixture['payment']->id, 'status' => 'posted']);
    }

    #[Test]
    public function payment_recorder_cannot_verify_their_own_payment(): void
    {
        $fixture = $this->paymentFixture();

        $this->expectException(ValidationException::class);
        app(RentPaymentVerificationService::class)->verify(
            $fixture['payment'],
            [['rent_charge_id' => $fixture['charge']->id, 'amount' => '40000.00']],
            $fixture['recorder']
        );
    }

    #[Test]
    public function payment_cannot_be_allocated_above_the_charge_balance(): void
    {
        $fixture = $this->paymentFixture();
        $fixture['payment']->update(['amount' => '120000.00']);

        $this->expectException(ValidationException::class);
        app(RentPaymentVerificationService::class)->verify(
            $fixture['payment'],
            [['rent_charge_id' => $fixture['charge']->id, 'amount' => '120000.00']],
            $fixture['verifier']
        );
    }
}
