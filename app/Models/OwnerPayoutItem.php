<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OwnerPayoutItem extends Model {
 protected $fillable=['owner_payout_id','item_type','source_id','description','amount'];
 protected function casts():array{return ['amount'=>'decimal:2'];}
 public function payout():BelongsTo{return $this->belongsTo(OwnerPayout::class,'owner_payout_id');}
}