<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Lease extends Model {
 protected $fillable=['lease_number','property_id','property_unit_id','tenant_id','starts_on','ends_on','rent_due_day','monthly_rent','security_deposit_required','security_deposit_paid','currency','billing_frequency','status','notice_given_on','terminated_on','termination_reason','special_terms','document_path','created_by'];
 protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date','notice_given_on'=>'date','terminated_on'=>'date','monthly_rent'=>'decimal:2','security_deposit_required'=>'decimal:2','security_deposit_paid'=>'decimal:2'];}
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
 public function property():BelongsTo{return $this->belongsTo(Property::class);}
 public function unit():BelongsTo{return $this->belongsTo(PropertyUnit::class,'property_unit_id');}
 public function terms():HasMany{return $this->hasMany(LeaseTerm::class);}
}