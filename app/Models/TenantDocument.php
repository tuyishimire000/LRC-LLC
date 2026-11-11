<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TenantDocument extends Model {protected $fillable=['tenant_id','document_type','original_filename','storage_path','mime_type','file_size_bytes','uploaded_by'];public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}}