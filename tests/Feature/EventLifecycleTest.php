<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ChannelWentLiveNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_schedule_event(): void
    {
        [$user, $org] = $this->creatorWithOrg();

        $this->actingAs($user)
            ->post(route('admin.events.store'), [
                'organization_id' => $org->id,
                'title' => 'Sunday',
                'access' => 'public',
                'chat_enabled' => '1',
                'show_listener_count' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'title' => 'Sunday',
            'organization_id' => $org->id,
            'status' => EventStatus::Scheduled->value,
        ]);
    }

    public function test_go_live_marks_event_and_notifies_followers(): void
    {
        Notification::fake();

        [$user, $org] = $this->creatorWithOrg();
        $follower = User::factory()->create();
        $org->followers()->attach($follower->id);

        $event = Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Live Show',
            'status' => EventStatus::Scheduled,
            'access' => EventAccess::Public,
        ]);

        $this->actingAs($user)
            ->post(route('admin.events.go-live', $event))
            ->assertRedirect();

        $event->refresh();
        $this->assertSame(EventStatus::Live, $event->status);
        $this->assertNotNull($event->stream_id);

        Notification::assertSentTo($follower, ChannelWentLiveNotification::class);
    }

    public function test_private_event_requires_password(): void
    {
        $org = Organization::query()->create(['name' => 'P', 'slug' => 'p', 'is_public' => true]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Secret',
            'status' => EventStatus::Scheduled,
            'access' => EventAccess::Private,
            'access_password' => 'secret123',
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Enter the password', false);

        $this->post(route('events.unlock', $event), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->post(route('events.unlock', $event), ['password' => 'secret123'])
            ->assertRedirect(route('events.show', $event));

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Secret', false);
    }

    public function test_webhook_ready_marks_linked_event_live(): void
    {
        config(['streaming.mediamtx.webhook_secret' => 'test-secret']);
        Notification::fake();

        [$user, $org] = $this->creatorWithOrg();
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Web',
            'status' => EventStatus::Scheduled,
            'access' => EventAccess::Public,
        ]);
        $stream = app(\App\Services\EventBroadcastService::class)->ensureStream($event);

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'ready',
            'path' => $stream->mediaPath(),
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $this->assertSame(EventStatus::Live, $event->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function creatorWithOrg(): array
    {
        $org = Organization::query()->create([
            'name' => 'Grace',
            'slug' => 'grace-'.uniqid(),
            'is_public' => true,
        ]);
        $user = User::factory()->create(['is_admin' => false]);
        $org->users()->attach($user->id, ['role' => OrgRole::Admin->value]);

        return [$user, $org];
    }
}
