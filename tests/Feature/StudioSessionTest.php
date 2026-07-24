<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StudioSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_live_auto_creates_event(): void
    {
        [$user, $stream] = $this->creatorStream();

        $url = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->postJson($url, ['title' => 'Sunday Service'])
            ->assertOk()
            ->assertJsonPath('event.title', 'Sunday Service')
            ->assertJsonPath('event.status', EventStatus::Live->value);

        $this->assertDatabaseHas('events', [
            'stream_id' => $stream->id,
            'title' => 'Sunday Service',
            'status' => EventStatus::Live->value,
        ]);
        $this->assertSame(StreamStatus::Live, $stream->fresh()->status);
    }

    public function test_go_live_without_title_uses_soundmix_default_name(): void
    {
        [$user, $stream] = $this->creatorStream();

        $url = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->postJson($url)
            ->assertOk()
            ->assertJsonPath('event.title', 'Live from Church')
            ->assertJsonPath('event.status', EventStatus::Live->value);
    }

    public function test_pause_keeps_event_open_and_resume_restores_live(): void
    {
        [$user, $stream] = $this->creatorStream();
        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);
        $pause = URL::temporarySignedRoute('studio.session.pause', now()->addHour(), ['stream' => $stream]);
        $resume = URL::temporarySignedRoute('studio.session.resume', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)->postJson($goLive)->assertOk();
        $eventId = Event::query()->where('stream_id', $stream->id)->value('id');

        $this->actingAs($user)
            ->postJson($pause)
            ->assertOk()
            ->assertJsonPath('event.status', EventStatus::Paused->value);

        $this->assertSame(EventStatus::Paused, Event::query()->find($eventId)->status);

        $this->actingAs($user)
            ->postJson($resume)
            ->assertOk()
            ->assertJsonPath('event.status', EventStatus::Live->value)
            ->assertJsonPath('event.id', $eventId);
    }

    public function test_end_live_closes_event_and_next_go_live_creates_new_event(): void
    {
        [$user, $stream] = $this->creatorStream();
        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);
        $end = URL::temporarySignedRoute('studio.session.end', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)->postJson($goLive, ['title' => 'First'])->assertOk();
        $firstId = (int) Event::query()->where('stream_id', $stream->id)->value('id');

        $this->actingAs($user)->postJson($end)->assertOk();
        $this->assertSame(EventStatus::Ended, Event::query()->find($firstId)->status);

        $this->actingAs($user)->postJson($goLive, ['title' => 'Second'])->assertOk();
        $second = Event::query()->where('stream_id', $stream->id)->where('status', EventStatus::Live)->first();
        $this->assertNotNull($second);
        $this->assertNotSame($firstId, $second->id);
        $this->assertSame('Second', $second->title);
    }

    public function test_create_event_then_go_live_uses_that_event(): void
    {
        [$user, $stream] = $this->creatorStream();
        $create = URL::temporarySignedRoute('studio.session.create-event', now()->addHour(), ['stream' => $stream]);
        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);

        $created = $this->actingAs($user)
            ->postJson($create, ['title' => 'Bible Study'])
            ->assertCreated()
            ->json('event');

        $this->actingAs($user)
            ->postJson($goLive, ['event_id' => $created['id']])
            ->assertOk()
            ->assertJsonPath('event.id', $created['id'])
            ->assertJsonPath('event.title', 'Bible Study')
            ->assertJsonPath('event.status', EventStatus::Live->value);
    }

    public function test_can_rename_live_event(): void
    {
        [$user, $stream] = $this->creatorStream();
        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);
        $rename = URL::temporarySignedRoute('studio.session.rename-event', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)->postJson($goLive, ['title' => 'Auto Live'])->assertOk();

        $this->actingAs($user)
            ->patchJson($rename, ['title' => 'Sunday Gathering'])
            ->assertOk()
            ->assertJsonPath('event.title', 'Sunday Gathering')
            ->assertJsonPath('event.status', EventStatus::Live->value);

        $this->assertDatabaseHas('events', [
            'stream_id' => $stream->id,
            'title' => 'Sunday Gathering',
            'status' => EventStatus::Live->value,
        ]);
    }

    public function test_webhook_not_ready_pauses_instead_of_ending(): void
    {
        config([
            'streaming.mediamtx.webhook_secret' => 'test-secret',
            'streaming.mediamtx.publisher_disconnect_grace_seconds' => 0,
            'streaming.mediamtx.not_ready_race_ms' => 0,
        ]);
        [, $stream] = $this->creatorStream();

        $event = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Web',
            'status' => EventStatus::Live,
        ]);

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'not_ready',
            'path' => $stream->mediaPath(),
        ], [
            'Authorization' => 'Bearer test-secret',
        ])->assertNoContent();

        $this->assertSame(EventStatus::Paused, $event->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Stream}
     */
    private function creatorStream(): array
    {
        $user = User::factory()->create();
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
        ]);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);

        return [$user, $stream];
    }
}
