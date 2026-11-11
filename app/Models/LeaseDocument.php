<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LeaseDocument extends Model {protected $fillable=['lease_id','document_type','original_filename','storage_path','mime_type','file_size_bytes','uploaded_by'];public function lease():BelongsTo{return $this->belongsTo(Lease::class);}}