<?php

namespace Tests\Feature;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ScriptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_church_can_set_and_poll_scripture(): void
    {
        [$user, $stream] = $this->churchStream();

        Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday',
            'status' => EventStatus::Live,
        ]);

        $store = URL::temporarySignedRoute('studio.scripture.store', now()->addHour(), ['stream' => $stream]);

        $response = $this->actingAs($user)
            ->postJson($store, ['ref' => 'John 3:16'])
            ->assertOk()
            ->assertJsonPath('scripture.ref', 'John 3:16')
            ->assertJsonPath('scripture.version', 'KJV');

        $this->assertStringContainsString(
            'God so loved the world',
            (string) data_get($response->json(), 'scripture.text'),
        );

        $this->getJson(route('scripture.show', $stream))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('scripture.ref', 'John 3:16')
            ->assertJsonPath('scripture.version', 'KJV');
    }

    public function test_radio_org_cannot_set_scripture(): void
    {
        [$user, $stream] = $this->radioStream();
        $store = URL::temporarySignedRoute('studio.scripture.store', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->postJson($store, ['ref' => 'John 3:16'])
            ->assertForbidden();
    }

    public function test_invalid_ref_returns_422(): void
    {
        [$user, $stream] = $this->churchStream();
        $store = URL::temporarySignedRoute('studio.scripture.store', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->postJson($store, ['ref' => 'NotABook 1:1'])
            ->assertStatus(422);
    }

    public function test_clear_scripture(): void
    {
        [$user, $stream] = $this->churchStream();
        $event = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday',
            'status' => EventStatus::Live,
            'scripture_ref' => 'John 3:16',
            'scripture_text' => 'For God so loved the world…',
            'scripture_updated_at' => now(),
        ]);

        $destroy = URL::temporarySignedRoute('studio.scripture.destroy', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)->deleteJson($destroy)->assertOk();

        $event->refresh();
        $this->assertNull($event->scripture_ref);
        $this->assertNull($event->scripture_text);
    }

    public function test_event_listen_page_includes_scripture_poll_for_church(): void
    {
        [, $stream] = $this->churchStream();
        $event = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday',
            'status' => EventStatus::Live,
        ]);

        $event->forceFill([
            'scripture_ref' => 'John 10:30',
            'scripture_text' => 'I and my Father are one.',
            'scripture_updated_at' => now(),
        ])->save();

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('data-scripture-url', false)
            ->assertSee('scripture-slide', false)
            ->assertSee('scripture-body', false)
            ->assertSee('scripture-board-name', false)
            ->assertSee('Scripture Board', false)
            ->assertSee('scripture-version', false)
            ->assertSee('(KJV)', false);
    }

    public function test_sanctum_api_scripture_for_desktop_studio(): void
    {
        [$user, $stream] = $this->churchStream();
        $token = $user->createToken('soundmix-studio-desktop')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/streams/{$stream->uuid}/scripture", ['ref' => 'Psalm 23:1'])
            ->assertOk()
            ->assertJsonPath('scripture.ref', 'Psalms 23:1');

        $this->withToken($token)
            ->getJson("/api/v1/streams/{$stream->uuid}/scripture")
            ->assertOk()
            ->assertJsonPath('scripture.ref', 'Psalms 23:1');
    }

    /** @return array{0: User, 1: Stream} */
    private function churchStream(): array
    {
        return $this->streamWithType(CreatorType::Church);
    }

    /** @return array{0: User, 1: Stream} */
    private function radioStream(): array
    {
        return $this->streamWithType(CreatorType::Radio);
    }

    /** @return array{0: User, 1: Stream} */
    private function streamWithType(CreatorType $type): array
    {
        $user = User::factory()->create();
        $org = Organization::query()->create([
            'name' => 'Channel',
            'slug' => 'channel-'.uniqid(),
            'creator_type' => $type,
        ]);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
        ]);

        return [$user, $stream];
    }
}
