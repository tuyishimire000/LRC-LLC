<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PropertyExpense extends Model {
 protected $fillable=['expense_number','property_id','management_agreement_id','category','description','incurred_on','amount','currency','charge_to_owner','status','receipt_path','recorded_by','approved_by','approved_at','approval_note'];
 protected function casts():array{return ['incurred_on'=>'date','amount'=>'decimal:2','charge_to_owner'=>'boolean','approved_at'=>'datetime'];}
 public function property():BelongsTo{return $this->belongsTo(Property::class);}
 public function agreement():BelongsTo{return $this->belongsTo(ManagementAgreement::class,'management_agreement_id');}
}