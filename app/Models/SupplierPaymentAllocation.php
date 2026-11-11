<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentAllocation extends Model
{
    protected $fillable = ['supplier_payment_id', 'property_expense_id', 'amount'];

    protected function casts(): array { return ['amount' => 'decimal:2']; }

    public function payment(): BelongsTo { return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id'); }
    public function expense(): BelongsTo { return $this->belongsTo(PropertyExpense::class, 'property_expense_id'); }
}
