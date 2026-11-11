<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentPayment extends Model
{
    protected $fillable = ['payment_number', 'tenant_id', 'amount', 'currency', 'payment_method', 'external_reference', 'paid_on', 'status', 'recorded_by', 'verified_by', 'verified_at', 'verification_note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'verified_at' => 'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function allocations(): HasMany { return $this->hasMany(RentPaymentAllocation::class); }
}