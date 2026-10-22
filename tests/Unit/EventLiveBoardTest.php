<?php

namespace Tests\Unit;

use App\Models\Event;
use Tests\TestCase;

class EventLiveBoardTest extends TestCase
{
    public function test_newer_scripture_wins_over_song(): void
    {
        $event = new Event([
            'scripture_ref' => 'John 3:16',
            'scripture_text' => 'For God so loved the world…',
            'scripture_updated_at' => now(),
            'song_title' => 'Amazing Grace',
            'song_text' => 'Amazing grace',
            'song_updated_at' => now()->subMinute(),
        ]);

        $this->assertSame('scripture', $event->liveBoardMode());
        $this->assertNotNull($event->liveScripturePayload());
        $this->assertNull($event->liveSongPayload());
    }

    public function test_newer_song_wins_over_scripture(): void
    {
        $event = new Event([
            'scripture_ref' => 'John 3:16',
            'scripture_text' => 'For God so loved the world…',
            'scripture_updated_at' => now()->subMinute(),
            'song_title' => 'Amazing Grace',
            'song_text' => 'Amazing grace',
            'song_updated_at' => now(),
        ]);

        $this->assertSame('song', $event->liveBoardMode());
        $this->assertNull($event->liveScripturePayload());
        $this->assertNull($event->liveNdiPayload());
    }

    public function test_newer_ndi_wins_over_scripture_and_song(): void
    {
        $event = new Event([
            'scripture_ref' => 'John 3:16',
            'scripture_text' => 'For God so loved the world…',
            'scripture_updated_at' => now()->subMinutes(2),
            'song_title' => 'Amazing Grace',
            'song_text' => 'Amazing grace',
            'song_updated_at' => now()->subMinute(),
            'ndi_source' => 'EasyWorship',
            'ndi_image_path' => 'ndi/1.jpg',
            'ndi_updated_at' => now(),
        ]);

        $this->assertSame('ndi', $event->liveBoardMode());
        $this->assertNull($event->liveScripturePayload());
        $this->assertNull($event->liveSongPayload());
        $this->assertSame('EasyWorship', $event->liveNdiPayload()['source']);
    }
}
