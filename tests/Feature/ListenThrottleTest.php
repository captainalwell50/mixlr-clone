<?php

namespace Tests\Feature;

use App\Enums\CreatorType;
use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListenThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_listen_poll_routes_do_not_share_one_ip_bucket(): void
    {
        $stream = $this->makePublicLiveStream();

        // Legacy throttle:120,1 keyed guests by domain|IP only, so status + scripture
        // + gallery + presence shared one 120/min counter. After the fix each named
        // listen-poll route has its own bucket — 50 hits on each must not 429.
        foreach ([
            route('listen.status', $stream),
            route('scripture.show', $stream),
            route('gallery.index', $stream),
        ] as $url) {
            for ($i = 0; $i < 50; $i++) {
                $this->getJson($url)->assertOk();
            }
        }

        $this->postJson(route('listen.presence', $stream), [
            'session_key' => 'throttle-test-session',
        ])->assertOk();
    }

    public function test_api_listen_payload_survives_adjacent_poll_traffic(): void
    {
        $stream = $this->makePublicLiveStream();

        for ($i = 0; $i < 40; $i++) {
            $this->getJson('/api/v1/listen/'.$stream->uuid.'/status')->assertOk();
            $this->getJson(route('scripture.show', $stream))->assertOk();
        }

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.uuid', $stream->uuid);
    }

    private function makePublicLiveStream(): Stream
    {
        $org = Organization::query()->create([
            'name' => 'Throttle Church',
            'slug' => 'throttle-'.uniqid(),
            'is_public' => true,
            'creator_type' => CreatorType::Church,
        ]);

        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Live,
            'is_public' => true,
            'started_at' => now(),
        ]);
    }
}
