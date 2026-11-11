<?php

namespace App\Services\Billing;

use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentPaymentVerificationService
{
    public function verify(RentPayment $payment, array $allocations, User $verifier, ?string $note = null): void
    {
        DB::transaction(function () use ($payment, $allocations, $verifier, $note) {
            $lockedPayment = RentPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedPayment->recorded_by === (int) $verifier->id) {\n                throw ValidationException::withMessages(['payment' => 'The person who recorded a payment cannot verify it. A second authorized staff member must check receipt.']);\n            }\n            if ($lockedPayment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Only pending payments can be verified.']);
            }

            $totalCents = 0;
            $normalised = [];
            foreach ($allocations as $allocation) {
                $charge = RentCharge::query()->whereKey($allocation['rent_charge_id'])->lockForUpdate()->firstOrFail();
                if ($charge->lease->tenant_id !== $lockedPayment->tenant_id) {
                    throw ValidationException::withMessages(['allocations' => 'A charge does not belong to this tenant.']);
                }
                if ($charge->currency !== $lockedPayment->currency) {
                    throw ValidationException::withMessages(['allocations' => 'Payment and rent charge currencies do not match.']);
                }
                $cents = (int) round((float) $allocation['amount'] * 100);
                $remaining = (int) round(((float) $charge->amount - (float) $charge->allocations()->sum('amount')) * 100);
                if ($cents <= 0 || $cents > $remaining) {
                    throw ValidationException::withMessages(['allocations' => 'An allocation exceeds the outstanding balance of its rent charge.']);
                }
                $normalised[] = [$charge->id, $cents];
                $totalCents += $cents;
            }

            if ($totalCents !== (int) round((float) $lockedPayment->amount * 100)) {
                throw ValidationException::withMessages(['allocations' => 'Allocations must equal the full payment amount. Split the payment across charges if necessary.']);
            }

            foreach ($normalised as [$chargeId, $cents]) {
                $lockedPayment->allocations()->create([
                    'rent_charge_id' => $chargeId,
                    'amount' => number_format($cents / 100, 2, '.', ''),
                ]);
            }

            $lockedPayment->update([
                'status' => 'verified',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'verification_note' => $note,
            ]);
        });
    }
}