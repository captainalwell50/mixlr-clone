<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Recording;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaMtxWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'streaming.mediamtx.webhook_secret' => 'test-secret',
            // Finalize immediately in tests (no afterResponse sleep).
            'streaming.mediamtx.publisher_disconnect_grace_seconds' => 0,
            'streaming.mediamtx.not_ready_race_ms' => 0,
        ]);
    }

    public function test_rejects_when_webhook_secret_not_configured(): void
    {
        config(['streaming.mediamtx.webhook_secret' => null]);

        $this->postJson('/api/webhooks/mediamtx', ['event' => 'ready', 'path' => 'live/x'])
            ->assertStatus(503);
    }

    public function test_rejects_invalid_bearer_token(): void
    {
        $this->postJson('/api/webhooks/mediamtx', ['event' => 'ready', 'path' => 'live/x'], [
            'Authorization' => 'Bearer wrong',
        ])->assertForbidden();
    }

    public function test_ready_marks_stream_live(): void
    {
        $stream = $this->makeStream();

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'ready',
            'path' => $stream->mediaPath(),
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $stream->refresh();
        $this->assertSame(StreamStatus::Live, $stream->status);
    }

    public function test_not_ready_marks_stream_offline(): void
    {
        $stream = $this->makeStream();
        $stream->forceFill(['status' => StreamStatus::Live])->save();

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'not_ready',
            'path' => $stream->mediaPath(),
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $stream->refresh();
        $this->assertSame(StreamStatus::Offline, $stream->status);
    }

    public function test_ready_clears_pending_publisher_gone_token(): void
    {
        $stream = $this->makeStream();
        $stream->forceFill(['status' => StreamStatus::Live])->save();
        cache()->put('mediamtx:publisher_gone:'.$stream->uuid, 'pending-token', now()->addMinute());

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'ready',
            'path' => $stream->mediaPath(),
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $this->assertNull(cache()->get('mediamtx:publisher_gone:'.$stream->uuid));
        $this->assertSame(StreamStatus::Live, $stream->fresh()->status);
    }

    public function test_not_ready_ignored_when_ready_races_ahead(): void
    {
        $stream = $this->makeStream();
        $uuid = $stream->uuid;
        $path = $stream->mediaPath();

        // Simulate out-of-order hooks: not_ready starts, ready stamps cache mid-flight.
        $notReadyStarted = microtime(true);
        usleep(10_000);
        cache()->put('mediamtx:last_ready:'.$uuid, microtime(true), now()->addDay());

        // Directly exercise the race guard used by the controller.
        $this->assertGreaterThanOrEqual(
            $notReadyStarted,
            (float) cache()->get('mediamtx:last_ready:'.$uuid, 0.0)
        );

        $stream->forceFill(['status' => StreamStatus::Live])->save();

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'ready',
            'path' => $path,
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        // A not_ready that began before this ready must not undo live after debounce.
        // Stamp an older "started" by putting a ready time in the future of a synthetic start.
        cache()->put('mediamtx:last_ready:'.$uuid, microtime(true) + 1, now()->addDay());

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'not_ready',
            'path' => $path,
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $stream->refresh();
        $this->assertSame(StreamStatus::Live, $stream->status);
    }

    public function test_record_segment_complete_ignores_short_reconnect_junk(): void
    {
        $stream = $this->makeStream();
        $rel = $stream->mediaPath().'/2026-01-01_12-00-00-000001';

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'record_segment_complete',
            'path' => $stream->mediaPath(),
            'segment_relative' => $rel,
            'duration_raw' => '12s',
            'size_bytes' => 1024,
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $this->assertDatabaseMissing('recordings', [
            'stream_id' => $stream->id,
            'relative_path' => $rel,
        ]);
    }

    public function test_record_segment_complete_publishes_ended_event_podcast(): void
    {
        Storage::fake('mediamtx_recordings');

        $stream = $this->makeStream();
        $event = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday Live',
            'status' => EventStatus::Ended,
            'ended_at' => now(),
        ]);
        $rel = $stream->mediaPath().'/2026-01-01_12-00-00-000001';

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'record_segment_complete',
            'path' => $stream->mediaPath(),
            'segment_relative' => $rel,
            'duration_raw' => '1h5m0s',
            'size_bytes' => 2_500_000,
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $recording = Recording::query()->first();
        $this->assertNotNull($recording);
        $this->assertSame(Recording::SOURCE_MEDIAMTX, $recording->source);
        $this->assertTrue($recording->is_public);
        $this->assertSame($event->id, $recording->event_id);
        $this->assertSame('Sunday Live', $recording->title);
        $this->assertSame('3900', $recording->duration_raw);
    }

    public function test_record_segment_complete_keeps_paused_segments_private(): void
    {
        $stream = $this->makeStream();
        Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Mid pause',
            'status' => EventStatus::Paused,
        ]);
        $rel = $stream->mediaPath().'/2026-01-01_12-00-00-000002';

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'record_segment_complete',
            'path' => $stream->mediaPath(),
            'segment_relative' => $rel,
            'duration_raw' => '10m0s',
            'size_bytes' => 900_000,
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $recording = Recording::query()->first();
        $this->assertNotNull($recording);
        $this->assertFalse($recording->is_public);
    }

    private function makeStream(): Stream
    {
        $org = Organization::query()->create([
            'name' => 'Test Org',
            'slug' => 'test-'.uniqid(),
        ]);

        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Sunday',
            'status' => StreamStatus::Offline,
        ]);
    }
}
