<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaMtxAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_read_for_known_stream(): void
    {
        $stream = $this->makeStream();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'path' => $stream->mediaPath(),
        ])->assertOk();
    }

    public function test_rejects_publish_for_unknown_path(): void
    {
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => 'live/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ])->assertForbidden();
    }

    public function test_allows_publish_with_stream_key(): void
    {
        config(['streaming.mediamtx.publish_secret' => null]);
        $stream = $this->makeStream();

        // Empty credentials → 401 so RTSP clients retry with a password.
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
        ])->assertUnauthorized();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
            'password' => $stream->stream_key,
        ])->assertOk();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
            'query' => 'pass='.$stream->stream_key,
        ])->assertOk();
    }

    public function test_allows_global_publish_secret(): void
    {
        config(['streaming.mediamtx.publish_secret' => 'church-secret']);
        $stream = $this->makeStream();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
            'password' => 'church-secret',
        ])->assertOk();
    }

    public function test_allows_read_and_loopback_publish_for_aac_sidecar(): void
    {
        config(['streaming.mediamtx.publish_secret' => 'church-secret']);
        $stream = $this->makeStream();
        $aacPath = $stream->mediaPath().'/aac';

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'path' => $aacPath,
        ])->assertOk();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $aacPath,
            'ip' => '127.0.0.1',
        ])->assertOk();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $aacPath,
            'ip' => '203.0.113.10',
            'password' => 'church-secret',
        ])->assertOk();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $aacPath,
            'ip' => '203.0.113.10',
        ])->assertUnauthorized();
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
