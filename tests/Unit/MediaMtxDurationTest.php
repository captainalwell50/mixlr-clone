<?php

namespace Tests\Unit;

use App\Support\MediaMtxDuration;
use PHPUnit\Framework\TestCase;

class MediaMtxDurationTest extends TestCase
{
    public function test_parses_go_style_durations(): void
    {
        $this->assertSame(3900.0, MediaMtxDuration::toSeconds('1h5m0s'));
        $this->assertSame(125.5, MediaMtxDuration::toSeconds('2m5.5s'));
        $this->assertSame(45.0, MediaMtxDuration::toSeconds('45s'));
        $this->assertSame(3600.0, MediaMtxDuration::toSeconds('1h'));
    }

    public function test_parses_numeric_seconds(): void
    {
        $this->assertSame(90.0, MediaMtxDuration::toSeconds('90'));
        $this->assertSame(12.5, MediaMtxDuration::toSeconds('12.5'));
    }

    public function test_rejects_empty_values(): void
    {
        $this->assertNull(MediaMtxDuration::toSeconds(null));
        $this->assertNull(MediaMtxDuration::toSeconds(''));
        $this->assertNull(MediaMtxDuration::toSeconds('nope'));
    }
}
