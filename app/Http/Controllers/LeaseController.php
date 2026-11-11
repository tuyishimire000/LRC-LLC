<?php
namespace App\Http\Controllers;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Tenant;
use App\Services\Leasing\LeaseRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
class LeaseController extends Controller {
 public function index():View{$leases=Lease::query()->with(['tenant','property','unit'])->latest()->paginate(20);return view('leases.index',compact('leases'));}
 public function create():View{return view('leases.create',['properties'=>Property::query()->orderBy('name')->get(),'units'=>PropertyUnit::query()->where('status','!=','inactive')->with('property')->orderBy('unit_code')->get(),'tenants'=>Tenant::query()->where('status','active')->orderBy('legal_name')->get()]);}
 public function store(Request $request,LeaseRules $rules):RedirectResponse {
  $data=$request->validate(['property_id'=>['required','integer','exists:properties,id'],'property_unit_id'=>['required','integer','exists:property_units,id'],'tenant_id'=>['required','integer','exists:tenants,id'],'starts_on'=>['required','date'],'ends_on'=>['nullable','date','after_or_equal:starts_on'],'rent_due_day'=>['required','integer','between:1,28'],'monthly_rent'=>['required','numeric','gt:0','decimal:0,2'],'security_deposit_required'=>['nullable','numeric','gte:0','decimal:0,2'],'special_terms'=>['nullable','string','max:10000']]);
  $data['security_deposit_required']=$data['security_deposit_required']??'0.00';$rules->validate($data);
  $lease=DB::transaction(function()use($data,$request){$lease=Lease::create([...$data,'lease_number'=>'LSE-'.Str::upper(Str::random(12)),'currency'=>'RWF','billing_frequency'=>'monthly','status'=>'draft','created_by'=>$request->user()->id]);$lease->terms()->create(['effective_from'=>$data['starts_on'],'monthly_rent'=>$data['monthly_rent'],'security_deposit_required'=>$data['security_deposit_required'],'change_reason'=>'Initial lease terms','recorded_by'=>$request->user()->id]);return $lease;});
  return redirect()->route('leases.show',$lease)->with('success','Lease saved as draft. Review and approve it before billing.');
 }
 public function show(Lease $lease):View{$lease->load(['tenant','property','unit','terms']);return view('leases.show',compact('lease'));}
 public function approve(Lease $lease,LeaseRules $rules):RedirectResponse {
  abort_unless($lease->status==='draft',422,'Only draft leases can be approved.');
  DB::transaction(function()use($lease,$rules){$unit=PropertyUnit::query()->whereKey($lease->property_unit_id)->lockForUpdate()->firstOrFail();$rules->validate(['property_id'=>$lease->property_id,'property_unit_id'=>$lease->property_unit_id,'starts_on'=>$lease->starts_on->toDateString(),'ends_on'=>$lease->ends_on?->toDateString(),'rent_due_day'=>$lease->rent_due_day,'monthly_rent'=>(string)$lease->monthly_rent,'security_deposit_required'=>(string)$lease->security_deposit_required]);$lease->update(['status'=>$lease->starts_on->isPast()?'active':'approved']);if($lease->status==='active')$unit->update(['status'=>'occupied']);});
  return back()->with('success','Lease approved. Billing will be enabled only after the billing module is installed.');
 }
 public function terminate(Request $request,Lease $lease):RedirectResponse {
  $data=$request->validate(['terminated_on'=>['required','date'],'termination_reason'=>['required','string','max:2000']]);
  abort_unless(in_array($lease->status,['approved','active'],true),422,'Only approved or active leases can be terminated.');
  abort_if($data['terminated_on']<$lease->starts_on->toDateString(),422,'Termination cannot predate the lease start.');
  DB::transaction(function()use($lease,$data){$unit=PropertyUnit::query()->whereKey($lease->property_unit_id)->lockForUpdate()->firstOrFail();$lease->update([...$data,'status'=>'terminated']);$otherOccupancy=Lease::query()->where('property_unit_id',$unit->id)->whereIn('status',['approved','active'])->whereDate('starts_on','<=',now()->toDateString())->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',now()->toDateString()))->exists();if(!$otherOccupancy)$unit->update(['status'=>'vacant']);});
  return back()->with('success','Lease terminated. Outstanding rent and deposit balances must be settled separately.');
 }
}