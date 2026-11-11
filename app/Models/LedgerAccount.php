<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LedgerAccount extends Model {
 protected $fillable=['account_code','name','account_type','normal_balance','is_control','is_active'];
 protected function casts():array{return ['is_control'=>'boolean','is_active'=>'boolean'];}
 public function lines():HasMany{return $this->hasMany(JournalLine::class);}
}