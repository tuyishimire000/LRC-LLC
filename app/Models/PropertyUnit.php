<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyUnit extends Model
{
    use SoftDeletes;

    protected $fillable = ['property_id', 'unit_code', 'unit_type', 'floor_label', 'bedrooms', 'bathrooms', 'area_sqm', 'status'];

    protected function casts(): array
    {
        return ['area_sqm' => 'decimal:2'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
