<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_code', 'tenant_type', 'legal_name', 'email', 'phone',
        'national_id_encrypted', 'company_registration', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'status',
    ];

    protected function casts(): array
    {
        return ['national_id_encrypted' => 'encrypted'];
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentPayment::class);
    }
}