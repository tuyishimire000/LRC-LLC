<?php
namespace App\Services\Leasing;
use App\Models\Lease;
use App\Models\PropertyUnit;
use Illuminate\Validation\ValidationException;
class LeaseRules {
 public function validate(array $data,?int $ignoreLeaseId=null):void {
  $start=$data['starts_on'];$end=$data['ends_on']??null;$due=(int)($data['rent_due_day']??1);
  if($end!==null && $end<$start)throw ValidationException::withMessages(['ends_on'=>'Lease end must be on or after its start.']);
  if($due<1||$due>28)throw ValidationException::withMessages(['rent_due_day'=>'Choose a due day from 1 to 28.']);
  foreach(['monthly_rent','security_deposit_required'] as $field){$v=(string)($data[$field]??'0');if(!preg_match('/^\d{1,13}(\.\d{1,2})?$/',$v))throw ValidationException::withMessages([$field=>'Enter a valid RWF amount with up to two decimal places.']);}
  if((float)$data['monthly_rent']<=0)throw ValidationException::withMessages(['monthly_rent'=>'Monthly rent must be greater than zero.']);
  $unit=PropertyUnit::query()->findOrFail($data['property_unit_id']);
  if((int)$unit->property_id!==(int)$data['property_id'])throw ValidationException::withMessages(['property_unit_id'=>'Selected unit does not belong to the selected property.']);
  $overlap=Lease::query()->where('property_unit_id',$unit->id)->whereIn('status',['approved','active'])->when($ignoreLeaseId,fn($q)=>$q->where('id','<>',$ignoreLeaseId))->whereDate('starts_on','<=',$end??'9999-12-31')->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',$start))->exists();
  if($overlap)throw ValidationException::withMessages(['property_unit_id'=>'An approved or active lease overlaps these dates for this unit.']);
 }
}