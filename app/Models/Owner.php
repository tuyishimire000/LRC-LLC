<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owner extends Model
{
    use SoftDeletes;

    protected $fillable = ['owner_code', 'owner_type', 'legal_name', 'email', 'phone', 'national_id_encrypted', 'tax_identifier', 'address', 'status'];

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_owners')
            ->withPivot(['ownership_percentage', 'effective_from', 'effective_to'])
            ->withTimestamps();
    }

    public function ownershipRecords(): HasMany
    {
        return $this->hasMany(PropertyOwner::class);
    }
}
