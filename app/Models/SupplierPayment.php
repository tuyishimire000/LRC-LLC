<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    protected $fillable = ['payment_number', 'amount', 'currency', 'payment_method', 'external_reference', 'paid_on', 'status', 'recorded_by', 'verified_by', 'verified_at', 'verification_note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'verified_at' => 'datetime'];
    }

    public function allocations(): HasMany { return $this->hasMany(SupplierPaymentAllocation::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
