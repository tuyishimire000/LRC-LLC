<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = ['property_code', 'management_type', 'name', 'property_type', 'country', 'province', 'district', 'sector', 'cell', 'village', 'street_address', 'description', 'status'];

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'property_owners')
            ->withPivot(['ownership_percentage', 'effective_from', 'effective_to'])
            ->withTimestamps();
    }

    public function ownershipRecords(): HasMany
    {
        return $this->hasMany(PropertyOwner::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }

    public function managementAgreements(): HasMany
    {
        return $this->hasMany(ManagementAgreement::class);
    }
}
