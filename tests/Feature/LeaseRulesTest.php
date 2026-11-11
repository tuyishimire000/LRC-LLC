<?php
namespace Tests\Feature;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyUnit;\nuse App\Models\Tenant;
use App\Services\Leasing\LeaseRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
class LeaseRulesTest extends TestCase {
 use RefreshDatabase;
 private function unit():array {
  $property=Property::create(['property_code'=>'PROP-TEST','management_type'=>'client_managed','name'=>'Test property','country'=>'RW','status'=>'active']);
  $unit=PropertyUnit::create(['property_id'=>$property->id,'unit_code'=>'A-01','status'=>'vacant']);
  return [$property,$unit];
 }
 private function payload(Property $property,PropertyUnit $unit):array {
  return ['property_id'=>$property->id,'property_unit_id'=>$unit->id,'starts_on'=>'2027-01-01','ends_on'=>'2027-12-31','rent_due_day'=>1,'monthly_rent'=>'250000.00','security_deposit_required'=>'250000.00'];
 }
 #[Test] public function it_accepts_a_valid_lease():void {[$p,$u]=$this->unit();(new LeaseRules())->validate($this->payload($p,$u));$this->assertTrue(true);}
 #[Test] public function it_rejects_a_unit_from_another_property():void {[$p,$u]=$this->unit();$other=Property::create(['property_code'=>'PROP-OTHER','management_type'=>'client_managed','name'=>'Other','country'=>'RW','status'=>'active']);$data=$this->payload($other,$u);$this->expectException(ValidationException::class);(new LeaseRules())->validate($data);}
 #[Test] public function it_rejects_overlapping_approved_leases():void {[$p,$u]=$this->unit();$data=$this->payload($p,$u);$tenant=Tenant::create(['tenant_code'=>'TEN-TEST','tenant_type'=>'individual','legal_name'=>'Test tenant','phone'=>'0780000000','status'=>'active']);Lease::create([...$data,'tenant_id'=>$tenant->id,'lease_number'=>'LSE-EXISTING','status'=>'approved','currency'=>'RWF','billing_frequency'=>'monthly']);$this->expectException(ValidationException::class);(new LeaseRules())->validate($data);}
}