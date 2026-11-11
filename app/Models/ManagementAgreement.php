<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementAgreement extends Model
{
    protected $fillable = ['agreement_number', 'property_id', 'starts_on', 'ends_on', 'fee_type', 'fee_value', 'payout_frequency', 'payout_day', 'status', 'document_path'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'fee_value' => 'decimal:2'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
