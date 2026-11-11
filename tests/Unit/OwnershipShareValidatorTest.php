<?php

namespace Tests\Unit;

use App\Services\Property\OwnershipShareValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OwnershipShareValidatorTest extends TestCase
{
    #[Test]
    public function it_accepts_multiple_shares_that_total_one_hundred_percent(): void
    {
        $validator = new OwnershipShareValidator();

        $validator->validate([
            ['ownership_percentage' => '60.00'],
            ['ownership_percentage' => '25.50'],
            ['ownership_percentage' => '14.50'],
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_shares_that_do_not_total_one_hundred_percent(): void
    {
        $this->expectException(ValidationException::class);

        (new OwnershipShareValidator())->validate([
            ['ownership_percentage' => '60.00'],
            ['ownership_percentage' => '30.00'],
        ]);
    }

    #[Test]
    public function it_rejects_shares_with_more_than_two_decimal_places(): void
    {
        $this->expectException(ValidationException::class);

        (new OwnershipShareValidator())->validate([
            ['ownership_percentage' => '50.001'],
            ['ownership_percentage' => '49.999'],
        ]);
    }

    #[Test]
    public function it_rejects_zero_or_negative_shares(): void
    {
        $this->expectException(ValidationException::class);

        (new OwnershipShareValidator())->validate([
            ['ownership_percentage' => '0'],
            ['ownership_percentage' => '100'],
        ]);
    }
}
