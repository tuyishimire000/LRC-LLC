<?php

namespace App\Services\Property;

use Illuminate\Validation\ValidationException;

class OwnershipShareValidator
{
    /**
     * Validate a proposed set of ownership shares for one effective-date period.
     *
     * The database stores individual shares; the complete active set must total
     * exactly 100.00% before a property can be activated for owner distributions.
     */
    public function validate(array $shares): void
    {
        if ($shares === []) {
            throw ValidationException::withMessages([
                'owners' => 'At least one owner is required before distributions can be enabled.',
            ]);
        }

        $totalBasisPoints = 0;

        foreach ($shares as $index => $share) {
            $value = filter_var($share['ownership_percentage'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($value === false || $value <= 0 || $value > 100) {
                throw ValidationException::withMessages([
                    "owners.{$index}.ownership_percentage" => 'Each ownership share must be greater than 0 and no more than 100 percent.',
                ]);
            }

            // Convert to basis points to avoid floating-point accumulation.
            $scaled = round($value * 100);

            if (abs(($value * 100) - $scaled) > 0.000001) {
                throw ValidationException::withMessages([
                    "owners.{$index}.ownership_percentage" => 'Ownership shares may have at most two decimal places.',
                ]);
            }

            $totalBasisPoints += (int) $scaled;
        }

        if ($totalBasisPoints !== 10000) {
            throw ValidationException::withMessages([
                'owners' => 'Active ownership shares must total exactly 100.00 percent.',
            ]);
        }
    }
}
