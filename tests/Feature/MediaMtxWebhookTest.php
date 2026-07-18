<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaMtxWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['streaming.mediamtx.webhook_secret' => 'test-secret']);
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

    public function test_record_segment_complete_creates_recording(): void
    {
        $stream = $this->makeStream();
        $rel = $stream->mediaPath().'/2026-01-01_12-00-00-000001';

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'record_segment_complete',
            'path' => $stream->mediaPath(),
            'segment_relative' => $rel,
            'duration_raw' => '1h0m0s',
            'size_bytes' => 1024,
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $this->assertDatabaseHas('recordings', [
            'stream_id' => $stream->id,
            'relative_path' => $rel,
        ]);

        $stream->refresh();
        $this->assertSame($rel, $stream->archive_path);
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
