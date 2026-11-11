<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyOwner extends Model
{
    protected $fillable = ['property_id', 'owner_id', 'ownership_percentage', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['ownership_percentage' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }
}
