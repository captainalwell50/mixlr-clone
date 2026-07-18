<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_post(): void
    {
        $event = $this->makeEvent(chatEnabled: true);

        $this->postJson(route('events.chat.store', $event), [
            'body' => 'Amen',
        ])->assertUnauthorized();
    }

    public function test_auth_user_posts_with_account_name(): void
    {
        $event = $this->makeEvent(chatEnabled: true);
        $user = User::factory()->create(['name' => 'Jordan']);

        $this->actingAs($user)->postJson(route('events.chat.store', $event), [
            'body' => 'Hello',
        ])->assertCreated()
            ->assertJsonPath('message.name', 'Jordan');
    }

    public function test_poll_returns_messages(): void
    {
        $event = $this->makeEvent(chatEnabled: true);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('events.chat.store', $event), [
            'body' => 'One',
        ])->assertCreated();

        $this->getJson(route('events.chat.index', $event))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonCount(1, 'messages');
    }

    public function test_disabled_chat_rejects_posts(): void
    {
        $event = $this->makeEvent(chatEnabled: false);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('events.chat.store', $event), [
            'body' => 'Nope',
        ])->assertForbidden();
    }

    private function makeEvent(bool $chatEnabled): Event
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
            'is_public' => true,
        ]);

        return Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Main',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'chat_enabled' => $chatEnabled,
        ]);
    }
}
