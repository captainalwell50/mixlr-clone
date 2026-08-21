<?php

namespace Tests\Feature;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Models\DisplaySong;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SongDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_church_can_create_cue_and_poll_song(): void
    {
        [$user, $stream] = $this->churchStream();

        Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday',
            'status' => EventStatus::Live,
        ]);

        $store = URL::temporarySignedRoute('studio.songs.store', now()->addHour(), ['stream' => $stream]);

        $create = $this->actingAs($user)
            ->postJson($store, [
                'title' => 'Amazing Grace',
                'body' => "Amazing grace, how sweet the sound\n\nThat saved a wretch like me",
            ])
            ->assertCreated()
            ->assertJsonPath('song.title', 'Amazing Grace')
            ->assertJsonPath('song.slide_count', 2);

        $songId = (int) data_get($create->json(), 'song.id');
        $cueUrl = (string) data_get($create->json(), 'song.cue_url');

        $this->actingAs($user)
            ->postJson($cueUrl, ['slide_index' => 0])
            ->assertOk()
            ->assertJsonPath('song.title', 'Amazing Grace')
            ->assertJsonPath('song.slide_index', 0);

        $this->getJson(route('scripture.show', $stream))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('song.title', 'Amazing Grace')
            ->assertJsonPath('song.text', 'Amazing grace, how sweet the sound');

        $next = URL::temporarySignedRoute('studio.songs.next', now()->addHour(), ['stream' => $stream]);
        $this->actingAs($user)
            ->postJson($next)
            ->assertOk()
            ->assertJsonPath('song.slide_index', 1)
            ->assertJsonPath('song.text', 'That saved a wretch like me');

        $this->assertDatabaseHas('display_songs', [
            'id' => $songId,
            'organization_id' => $stream->organization_id,
            'title' => 'Amazing Grace',
        ]);
    }

    public function test_radio_org_cannot_manage_songs(): void
    {
        [$user, $stream] = $this->radioStream();
        $store = URL::temporarySignedRoute('studio.songs.store', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->postJson($store, ['title' => 'Offer', 'body' => 'Account 123'])
            ->assertForbidden();
    }

    public function test_clear_song_cue(): void
    {
        [$user, $stream] = $this->churchStream();
        $song = DisplaySong::query()->create([
            'organization_id' => $stream->organization_id,
            'title' => 'Prayer',
            'slides' => ['Lift your hearts'],
        ]);
        $event = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday',
            'status' => EventStatus::Live,
            'song_id' => $song->id,
            'song_title' => 'Prayer',
            'song_text' => 'Lift your hearts',
            'song_slide_index' => 0,
            'song_slide_count' => 1,
            'song_updated_at' => now(),
        ]);

        $clear = URL::temporarySignedRoute('studio.songs.clear', now()->addHour(), ['stream' => $stream]);
        $this->actingAs($user)->deleteJson($clear)->assertOk();

        $event->refresh();
        $this->assertNull($event->song_title);
        $this->assertNull($event->song_text);
    }

    public function test_sanctum_api_song_for_desktop_studio(): void
    {
        [$user, $stream] = $this->churchStream();
        $token = $user->createToken('soundmix-studio-desktop')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson("/api/v1/streams/{$stream->uuid}/songs", [
                'title' => 'Offering',
                'slides' => ['Account: 0123456789', 'Thank you'],
            ])
            ->assertCreated();

        $songId = (int) data_get($create->json(), 'song.id');

        $this->withToken($token)
            ->postJson("/api/v1/streams/{$stream->uuid}/songs/{$songId}/cue")
            ->assertOk()
            ->assertJsonPath('song.title', 'Offering');

        $this->withToken($token)
            ->getJson("/api/v1/streams/{$stream->uuid}/songs/cue")
            ->assertOk()
            ->assertJsonPath('song.title', 'Offering');
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
