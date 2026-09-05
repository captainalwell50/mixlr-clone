<?php

namespace Tests\Unit;

use App\Support\StudioExpiry;
use Tests\TestCase;

class StudioExpiryTest extends TestCase
{
    public function test_studio_urls_outlast_a_day_long_broadcast(): void
    {
        $this->assertSame(525_600, StudioExpiry::LIFETIME_MINUTES);
        $this->assertSame(525_600, StudioExpiry::minutes());
        $this->assertTrue(StudioExpiry::at()->greaterThan(now()->addDay()));
    }
}
