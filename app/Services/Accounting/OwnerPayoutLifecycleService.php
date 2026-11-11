<?php

namespace App\Services\Accounting;

use App\Models\OwnerPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerPayoutLifecycleService
{
    public function approve(OwnerPayout $payout, User $approver, ?string $note = null): OwnerPayout
    {
        return DB::transaction(function () use ($payout, $approver, $note) {
            $locked = OwnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['payout' => 'Only draft payouts can be approved.']);
            }
            if ((int) $locked->prepared_by === (int) $approver->id) {
                throw ValidationException::withMessages(['payout' => 'The person who prepared a payout cannot approve it.']);
            }
            if ((float) $locked->net_amount < 0) {
                throw ValidationException::withMessages(['payout' => 'A negative payout cannot be approved.']);
            }
            $locked->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ]);
            return $locked->fresh(['owner', 'property', 'items']);
        });
    }

    public function recordTransfer(OwnerPayout $payout, User $operator, string $reference): OwnerPayout
    {
        return DB::transaction(function () use ($payout, $operator, $reference) {
            $locked = OwnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['payout' => 'Only approved payouts can be marked as transferred.']);
            }
            if ((int) $locked->approved_by === (int) $operator->id) {
                throw ValidationException::withMessages(['payout' => 'The payout approver cannot also record the transfer.']);
            }
            $locked->update([
                'status' => 'paid',
                'paid_by' => $operator->id,
                'paid_at' => now(),
                'payout_reference' => $reference,
            ]);
            return $locked->fresh(['owner', 'property', 'items']);
        });
    }
}
