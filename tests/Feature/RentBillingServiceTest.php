<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Services\Billing\RentBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RentBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function leaseStartingMidMonth(): Lease
    {
        $property = Property::create([
            'property_code' => 'PRORATE-PROP',
            'management_type' => 'client_managed',
            'name' => 'Proration test property',
            'country' => 'RW',
            'status' => 'active',
        ]);
        $unit = PropertyUnit::create([
            'property_id' => $property->id,
            'unit_code' => 'P-01',
            'status' => 'occupied',
        ]);
        $tenant = Tenant::create([
            'tenant_code' => 'PRORATE-TEN',
            'tenant_type' => 'individual',
            'legal_name' => 'Proration test tenant',
            'phone' => '0780000002',
            'status' => 'active',
        ]);

        return Lease::create([
            'lease_number' => 'PRORATE-LEASE',
            'property_id' => $property->id,
            'property_unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => '2026-11-15',
            'ends_on' => null,
            'rent_due_day' => 1,
            'monthly_rent' => '300000.00',
            'security_deposit_required' => '0.00',
            'security_deposit_paid' => '0.00',
            'currency' => 'RWF',
            'billing_frequency' => 'monthly',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_prorates_a_partial_first_month_and_does_not_duplicate_charges(): void
    {
        $this->leaseStartingMidMonth();
        $service = app(RentBillingService::class);

        $firstRun = $service->generateForMonth(CarbonImmutable::parse('2026-11-01'));
        $secondRun = $service->generateForMonth(CarbonImmutable::parse('2026-11-01'));

        $charge = RentCharge::query()->sole();

        $this->assertSame(1, $firstRun);
        $this->assertSame(0, $secondRun);
        $this->assertSame('160000.00', $charge->amount);
        $this->assertSame('2026-11-15', $charge->due_on->toDateString());
    }
}
