<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEngageTest extends TestCase
{
    use RefreshDatabase;

    public function test_presence_updates_listener_count(): void
    {
        $event = $this->makeEvent();

        $this->postJson(route('events.presence', $event), [
            'session_key' => 'abc123',
        ])->assertOk()
            ->assertJsonPath('listeners', 1);

        $this->assertDatabaseHas('listener_sessions', [
            'event_id' => $event->id,
            'session_key' => 'abc123',
        ]);
    }

    public function test_heart_requires_auth(): void
    {
        $event = $this->makeEvent();

        $this->postJson(route('events.heart', $event))
            ->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson(route('events.heart', $event))
            ->assertOk()
            ->assertJsonPath('hearted', true)
            ->assertJsonPath('hearts', 1);
    }

    public function test_chat_requires_auth(): void
    {
        $event = $this->makeEvent();

        $this->postJson(route('events.chat.store', $event), [
            'body' => 'Hi',
        ])->assertUnauthorized();

        $user = User::factory()->create(['name' => 'Pat']);
        $this->actingAs($user)
            ->postJson(route('events.chat.store', $event), [
                'body' => 'Hi',
            ])
            ->assertCreated()
            ->assertJsonPath('message.name', 'Pat');
    }

    private function makeEvent(): Event
    {
        $org = Organization::query()->create([
            'name' => 'Ch',
            'slug' => 'ch-'.uniqid(),
            'is_public' => true,
        ]);

        return Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Show',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'chat_enabled' => true,
            'show_listener_count' => true,
            'started_at' => now(),
        ]);
    }
}
