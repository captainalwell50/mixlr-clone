<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListenerPresenceUnifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_stream_presence_appears_on_event_count(): void
    {
        [$stream, $event] = $this->makeLinkedLiveRoom();

        $this->postJson(route('api.listen.presence', $stream), [
            'session_key' => 'mobile-listener-1',
        ])->assertOk()
            ->assertJsonPath('listeners', 1);

        $this->postJson(route('events.presence', $event), [
            'session_key' => 'web-listener-1',
        ])->assertOk()
            ->assertJsonPath('listeners', 2);

        $this->assertSame(2, $stream->fresh()->activeListenerCount());
        $this->assertSame(2, $event->fresh()->activeListenerCount());
    }

    public function test_same_session_key_is_not_double_counted_across_tables(): void
    {
        [$stream, $event] = $this->makeLinkedLiveRoom();

        $this->postJson(route('api.listen.presence', $stream), [
            'session_key' => 'shared-sid',
        ])->assertOk()
            ->assertJsonPath('listeners', 1);

        $this->postJson(route('events.presence', $event), [
            'session_key' => 'shared-sid',
        ])->assertOk()
            ->assertJsonPath('listeners', 1);

        $this->assertSame(1, $stream->fresh()->activeListenerCount());
    }

    public function test_authenticated_user_on_web_and_mobile_counts_once(): void
    {
        [$stream, $event] = $this->makeLinkedLiveRoom();
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson(route('api.listen.presence', $stream), [
            'session_key' => 'mobile-device',
        ])->assertOk()
            ->assertJsonPath('listeners', 1);

        $this->actingAs($user)
            ->postJson(route('events.presence', $event), [
                'session_key' => 'web-browser',
            ])->assertOk()
            ->assertJsonPath('listeners', 1);
    }

    /** @return array{0: Stream, 1: Event} */
    private function makeLinkedLiveRoom(): array
    {
        $org = Organization::query()->create([
            'name' => 'Presence Church',
            'slug' => 'presence-'.uniqid(),
            'is_public' => true,
        ]);

        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Live,
            'is_public' => true,
            'started_at' => now(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Sunday Live',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'chat_enabled' => true,
            'show_listener_count' => true,
            'started_at' => now(),
        ]);

        return [$stream, $event];
    }
}
