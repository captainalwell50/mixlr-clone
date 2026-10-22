<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Jobs\AssembleEventRecordingJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use App\Support\PublisherClaim;
use App\Support\StudioExpiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
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

    public function test_end_live_marks_offline_without_waiting_for_recording_assembly(): void
    {
        Bus::fake([AssembleEventRecordingJob::class]);

        [$user, $stream] = $this->creatorStream();
        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);
        $end = URL::temporarySignedRoute('studio.session.end', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)->postJson($goLive, ['title' => 'Sunday'])->assertOk();

        $this->actingAs($user)
            ->postJson($end)
            ->assertOk()
            ->assertJsonPath('event.status', EventStatus::Ended->value);

        $this->assertSame(StreamStatus::Offline, $stream->fresh()->status);
        Bus::assertDispatched(AssembleEventRecordingJob::class);
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

    public function test_session_cookie_is_permanent_for_long_broadcasts(): void
    {
        $this->assertSame(StudioExpiry::LIFETIME_MINUTES, (int) config('session.lifetime'));
        $this->assertFalse((bool) config('session.expire_on_close'));

        $cookie = collect($this->get('/how-it-works')->headers->getCookies())
            ->first(fn ($item) => $item->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertGreaterThanOrEqual((StudioExpiry::LIFETIME_MINUTES * 60) - 60, $cookie->getMaxAge());
    }

    public function test_login_session_survives_hours_of_idle_broadcast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/how-it-works')->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->travel(3)->hours();

        $this->get('/how-it-works')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_session_show_returns_csrf_for_studio_keepalive(): void
    {
        [$user, $stream] = $this->creatorStream();
        $url = URL::temporarySignedRoute('studio.session.show', StudioExpiry::at(), ['stream' => $stream]);

        $csrf = $this->actingAs($user)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('stream_id', $stream->id)
            ->json('csrf');

        $this->assertIsString($csrf);
        $this->assertNotSame('', $csrf);
    }

    public function test_session_show_includes_reload_at_and_command_bumps_it(): void
    {
        [$user, $stream] = $this->creatorStream();
        $url = URL::temporarySignedRoute('studio.session.show', StudioExpiry::at(), ['stream' => $stream]);

        $before = $this->actingAs($user)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('stream_id', $stream->id)
            ->json('reload_at');

        $this->assertSame(0, $before);

        $this->artisan('studio:reload', ['--stream' => $stream->uuid])
            ->assertSuccessful();

        $after = $this->actingAs($user)
            ->getJson($url)
            ->assertOk()
            ->json('reload_at');

        $this->assertGreaterThan(0, $after);
        $this->assertGreaterThanOrEqual($before, $after);
    }

    public function test_studio_end_url_from_page_still_works_after_overnight_live(): void
    {
        [$user, $stream] = $this->creatorStream();
        $studioUrl = URL::temporarySignedRoute('studio.stream', StudioExpiry::at(), ['stream' => $stream]);

        $html = $this->actingAs($user)->get($studioUrl)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-session-end-url="[^"]+"/', $html);
        preg_match('/data-session-end-url="([^"]+)"/', $html, $matches);
        $endUrl = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);

        $this->assertTrue(URL::hasValidSignature(Request::create($endUrl)));

        $goLive = URL::temporarySignedRoute('studio.session.go-live', StudioExpiry::at(), ['stream' => $stream]);
        $this->actingAs($user)->postJson($goLive, ['title' => 'Overnight'])->assertOk();

        $this->travel(13)->hours();

        $this->assertTrue(URL::hasValidSignature(Request::create($endUrl)));
        $this->actingAs($user)->postJson($endUrl)->assertOk();
        $this->assertSame(EventStatus::Ended, Event::query()->where('stream_id', $stream->id)->first()?->status);
    }

    public function test_replacement_keeps_the_event_live_and_blocks_the_old_claim(): void
    {
        [$user, $stream] = $this->creatorStream();
        config([
            'streaming.mediamtx.webhook_secret' => 'test-secret',
            'streaming.mediamtx.publisher_disconnect_grace_seconds' => 0,
            'streaming.mediamtx.not_ready_race_ms' => 0,
        ]);

        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);
        $claimUrl = URL::temporarySignedRoute('studio.session.publisher-claim', now()->addHour(), ['stream' => $stream]);
        $this->actingAs($user)->postJson($goLive)->assertOk();

        $claimA = '11111111-1111-4111-8111-111111111111';
        $claimB = '22222222-2222-4222-8222-222222222222';

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimA, 'intent' => 'claim'])
            ->assertOk()
            ->assertJsonPath('owns', true)
            ->assertJsonPath('replaced', false);

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'ready',
            'path' => 'live/'.$stream->uuid,
        ], ['Authorization' => 'Bearer test-secret'])->assertNoContent();

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimB, 'intent' => 'claim'])
            ->assertOk()
            ->assertJsonPath('owns', true)
            ->assertJsonPath('replaced', false);

        cache()->put('mediamtx:last_ready:'.$stream->uuid, microtime(true) + 1, now()->addDay());
        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'not_ready',
            'path' => 'live/'.$stream->uuid,
        ], ['Authorization' => 'Bearer test-secret'])->assertNoContent();

        $event = Event::query()->where('stream_id', $stream->id)->first();
        $this->assertSame(EventStatus::Live, $event->status);
        $this->assertSame($claimB, PublisherClaim::current($stream->uuid));

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimA, 'intent' => 'check'])
            ->assertOk()
            ->assertJsonPath('owns', false)
            ->assertJsonPath('replaced', true);

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimA, 'intent' => 'claim'])
            ->assertOk()
            ->assertJsonPath('owns', true)
            ->assertJsonPath('replaced', false);

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimB, 'intent' => 'check'])
            ->assertOk()
            ->assertJsonPath('replaced', true);
    }

    public function test_a_real_publisher_drop_clears_ownership_so_the_same_client_can_recover(): void
    {
        [$user, $stream] = $this->creatorStream();
        config([
            'streaming.mediamtx.webhook_secret' => 'test-secret',
            'streaming.mediamtx.publisher_disconnect_grace_seconds' => 0,
            'streaming.mediamtx.not_ready_race_ms' => 0,
        ]);

        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);
        $claimUrl = URL::temporarySignedRoute('studio.session.publisher-claim', now()->addHour(), ['stream' => $stream]);
        $this->actingAs($user)->postJson($goLive)->assertOk();

        $claimA = '33333333-3333-4333-8333-333333333333';
        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimA, 'intent' => 'claim'])
            ->assertOk();
        // The live client claimed when it connected, not in the seconds before this drop.
        PublisherClaim::remember($stream->uuid, $claimA, microtime(true) - 30);

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'not_ready',
            'path' => 'live/'.$stream->uuid,
        ], ['Authorization' => 'Bearer test-secret'])->assertNoContent();

        $this->assertNull(PublisherClaim::current($stream->uuid));
        $this->assertSame(EventStatus::Paused, Event::query()->where('stream_id', $stream->id)->first()->status);

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimA, 'intent' => 'check'])
            ->assertOk()
            ->assertJsonPath('owns', false)
            ->assertJsonPath('replaced', false);
    }

    public function test_native_clients_use_the_same_publisher_claim_as_web_studio(): void
    {
        [$user, $stream] = $this->creatorStream();
        Sanctum::actingAs($user);

        $claimA = '11111111-1111-4111-8111-111111111111';
        $claimB = '22222222-2222-4222-8222-222222222222';
        $url = '/api/v1/streams/'.$stream->uuid.'/publisher-claim';

        $this->postJson($url, ['claim' => $claimA, 'intent' => 'claim'])
            ->assertOk()
            ->assertJsonPath('owns', true)
            ->assertJsonPath('replaced', false);

        $this->postJson($url, ['claim' => $claimB, 'intent' => 'check'])
            ->assertOk()
            ->assertJsonPath('owns', false)
            ->assertJsonPath('replaced', true);

        $this->postJson($url, ['claim' => $claimA, 'intent' => 'release'])
            ->assertOk();
        $this->assertNull(PublisherClaim::current($stream->uuid));
    }

    public function test_a_takeover_claim_survives_not_ready_when_ready_loses_the_race(): void
    {
        [$user, $stream] = $this->creatorStream();
        config([
            'streaming.mediamtx.webhook_secret' => 'test-secret',
            'streaming.mediamtx.publisher_disconnect_grace_seconds' => 0,
            'streaming.mediamtx.not_ready_race_ms' => 0,
        ]);

        $goLive = URL::temporarySignedRoute('studio.session.go-live', now()->addHour(), ['stream' => $stream]);
        $claimUrl = URL::temporarySignedRoute('studio.session.publisher-claim', now()->addHour(), ['stream' => $stream]);
        $this->actingAs($user)->postJson($goLive)->assertOk();

        $claimA = '11111111-1111-4111-8111-111111111111';
        $claimB = '44444444-4444-4444-8444-444444444444';

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimA, 'intent' => 'claim'])
            ->assertOk();
        PublisherClaim::remember($stream->uuid, $claimA, microtime(true) - 30);

        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'ready',
            'path' => 'live/'.$stream->uuid,
        ], ['Authorization' => 'Bearer test-secret'])->assertNoContent();

        // B claims, then MediaMTX not_ready arrives before the new ready stamp.
        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimB, 'intent' => 'claim'])
            ->assertOk()
            ->assertJsonPath('owns', true);

        cache()->put('mediamtx:last_ready:'.$stream->uuid, microtime(true) - 30, now()->addDay());
        $this->postJson('/api/webhooks/mediamtx', [
            'event' => 'not_ready',
            'path' => 'live/'.$stream->uuid,
        ], ['Authorization' => 'Bearer test-secret'])->assertNoContent();

        $this->assertSame($claimB, PublisherClaim::current($stream->uuid));
        $this->assertSame(EventStatus::Live, Event::query()->where('stream_id', $stream->id)->first()->status);

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimA, 'intent' => 'check'])
            ->assertOk()
            ->assertJsonPath('owns', false)
            ->assertJsonPath('replaced', true);

        $this->actingAs($user)
            ->postJson($claimUrl, ['claim' => $claimB, 'intent' => 'release'])
            ->assertOk();
        $this->assertNull(PublisherClaim::current($stream->uuid));
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
        $this->grantFreeBroadcast($org);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);

        return [$user, $stream];
    }
}
