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

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
        ])->assertForbidden();

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
