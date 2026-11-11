<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentCharge extends Model
{
    protected $fillable = ['charge_number', 'lease_id', 'billing_period', 'charged_on', 'due_on', 'amount', 'currency', 'status', 'description', 'generated_by'];

    protected function casts(): array
    {
        return ['billing_period' => 'date', 'charged_on' => 'date', 'due_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function lease(): BelongsTo { return $this->belongsTo(Lease::class); }
    public function allocations(): HasMany { return $this->hasMany(RentPaymentAllocation::class); }

    public function getAllocatedAmountAttribute(): string
    {
        $value = $this->relationLoaded('allocations')
            ? $this->allocations->sum(fn ($allocation) => (float) $allocation->amount)
            : (float) $this->allocations()->sum('amount');
        return number_format($value, 2, '.', '');
    }

    public function getOutstandingAmountAttribute(): string
    {
        return number_format(max(0, (float) $this->amount - (float) $this->allocated_amount), 2, '.', '');
    }

    public function getPaymentStatusAttribute(): string
    {
        $outstanding = (float) $this->outstanding_amount;
        if ($outstanding <= 0) return 'paid';
        if ((float) $this->allocated_amount > 0) return 'partial';
        return $this->due_on->copy()->endOfDay()->isPast() ? 'overdue' : 'unpaid';
    }
}