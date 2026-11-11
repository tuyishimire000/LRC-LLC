<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class OwnerPayout extends Model {
 protected $fillable=['payout_number','owner_id','property_id','period_start','period_end','gross_collected','management_fee','owner_expenses','net_amount','currency','status','prepared_by','approved_by','approved_at','paid_by','paid_at','payout_reference','approval_note'];
 protected function casts():array{return ['period_start'=>'date','period_end'=>'date','gross_collected'=>'decimal:2','management_fee'=>'decimal:2','owner_expenses'=>'decimal:2','net_amount'=>'decimal:2','approved_at'=>'datetime','paid_at'=>'datetime'];}
 public function owner():BelongsTo{return $this->belongsTo(Owner::class);}
 public function property():BelongsTo{return $this->belongsTo(Property::class);}
 public function items():HasMany{return $this->hasMany(OwnerPayoutItem::class);}
}