<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    #[Test]
    public function the_application_health_endpoint_responds_successfully(): void
    {
        $this->get('/up')->assertOk();
    }
}
