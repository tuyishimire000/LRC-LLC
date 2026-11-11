<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class JournalEntry extends Model {
 protected $fillable=['entry_number','entry_date','description','source_type','source_id','status','created_by','posted_by','posted_at','reversal_of_id'];
 protected function casts():array{return ['entry_date'=>'date','posted_at'=>'datetime'];}
 public function lines():HasMany{return $this->hasMany(JournalLine::class);}
}