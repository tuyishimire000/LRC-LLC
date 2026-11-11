<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LeaseTerm extends Model {
 protected $fillable=['lease_id','effective_from','effective_to','monthly_rent','security_deposit_required','change_reason','recorded_by'];
 protected function casts():array{return ['effective_from'=>'date','effective_to'=>'date','monthly_rent'=>'decimal:2','security_deposit_required'=>'decimal:2'];}
 public function lease():BelongsTo{return $this->belongsTo(Lease::class);}
}