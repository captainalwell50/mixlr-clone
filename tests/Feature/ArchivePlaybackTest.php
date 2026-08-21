<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Recording;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivePlaybackTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_play_page(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording([
            'title' => 'Sunday Podcast',
            'duration_raw' => '3195',
        ]);

        $this->get(route('archive.play', $recording))
            ->assertOk()
            ->assertSee('Sunday Podcast', false)
            ->assertSee(route('archive.file', $recording), false)
            ->assertSee('data-duration-seconds="3195"', false)
            ->assertSee('53:15', false);
    }

    public function test_guest_can_stream_recording_file(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording();
        Storage::disk('mediamtx_recordings')->put($recording->relative_path, 'fake-audio-bytes');

        $this->get(route('archive.file', $recording))
            ->assertOk();
    }

    public function test_archive_index_lists_channels_not_flat_recordings(): void
    {
        $recording = $this->makeRecording(['title' => 'Hidden Flat Title']);
        $org = $recording->stream->organization;

        $this->get(route('archive.index'))
            ->assertOk()
            ->assertSee($org->name, false)
            ->assertSee('podcast', false)
            ->assertDontSee('Hidden Flat Title', false);
    }

    public function test_archive_channel_lists_podcasts(): void
    {
        $recording = $this->makeRecording(['title' => 'Morning Dew Podcast']);
        $org = $recording->stream->organization;

        $this->get(route('archive.channel', $org))
            ->assertOk()
            ->assertSee('Morning Dew Podcast', false)
            ->assertSee($org->name, false)
            ->assertDontSee('PODCASTS', false)
            ->assertDontSee('Recorded lives from this channel', false)
            ->assertDontSee('>Delete<', false);
    }

    public function test_archive_channel_shows_delete_only_for_managers(): void
    {
        $recording = $this->makeRecording(['title' => 'Manager Delete Podcast']);
        $org = $recording->stream->organization;
        $manager = \App\Models\User::factory()->create();
        $org->users()->attach($manager->id, ['role' => \App\Enums\OrgRole::Owner->value]);
        $outsider = \App\Models\User::factory()->create();

        $this->actingAs($manager)
            ->get(route('archive.channel', $org))
            ->assertOk()
            ->assertSee('>Delete<', false)
            ->assertSee('Delete this podcast permanently?', false);

        $this->actingAs($outsider)
            ->get(route('archive.channel', $org))
            ->assertOk()
            ->assertDontSee('>Delete<', false);
    }

    public function test_manager_can_delete_podcast_from_channel_page(): void
    {
        \Illuminate\Support\Facades\Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording(['title' => 'Remove Me']);
        $org = $recording->stream->organization;
        $manager = \App\Models\User::factory()->create();
        $org->users()->attach($manager->id, ['role' => \App\Enums\OrgRole::Owner->value]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'recordings.destroy',
            now()->addHours(12),
            ['stream' => $recording->stream, 'recording' => $recording],
        );

        $this->actingAs($manager)
            ->delete($url)
            ->assertRedirect();

        $this->assertDatabaseMissing('recordings', ['id' => $recording->id]);
    }

    public function test_archive_prefers_event_name_over_channel_stream_title(): void
    {
        $recording = $this->makeRecording([
            'title' => 'Church', // same as channel/stream — treat as generic
        ]);
        $event = \App\Models\Event::query()->create([
            'organization_id' => $recording->stream->organization_id,
            'stream_id' => $recording->stream_id,
            'title' => 'Sunday Service',
            'status' => \App\Enums\EventStatus::Ended,
        ]);
        $recording->forceFill(['event_id' => $event->id])->save();

        $this->assertSame('Sunday Service', $recording->fresh(['event', 'stream.organization'])->displayTitle());

        $this->get(route('archive.channel', $recording->stream->organization))
            ->assertOk()
            ->assertSee('Sunday Service', false);
    }

    public function test_archive_uses_live_date_when_no_event_instead_of_channel_name(): void
    {
        $recording = $this->makeRecording([
            'title' => null,
            'completed_at' => now()->setTime(20, 44),
        ]);
        // stream title matches channel name in makeRecording fixtures via org/stream setup
        $recording->stream->forceFill(['title' => 'Church'])->save();

        $label = $recording->fresh(['event', 'stream.organization'])->displayTitle();
        $this->assertStringStartsWith('Live ·', $label);
        $this->assertStringNotContainsString('Church', $label);
    }

    public function test_archive_index_hides_channels_with_only_unpublished_recordings(): void
    {
        Storage::fake('mediamtx_recordings');
        $public = $this->makeRecording(['title' => 'Public One']);
        Recording::query()->create([
            'stream_id' => $public->stream_id,
            'source' => Recording::SOURCE_MEDIAMTX,
            'is_public' => false,
            'title' => 'Draft Only',
            'relative_path' => $public->stream->mediaPath().'/draft-only.mp4',
            'duration_raw' => '600',
            'size_bytes' => 12_000_000,
            'completed_at' => now(),
        ]);

        $this->get(route('archive.index'))
            ->assertOk()
            ->assertSee($public->stream->organization->name, false);

        $this->get(route('archive.channel', $public->stream->organization))
            ->assertOk()
            ->assertSee('Public One', false)
            ->assertDontSee('Draft Only', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRecording(array $overrides = []): Recording
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
            'is_public' => true,
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);
        $rel = $stream->mediaPath().'/part1.mp4';

        return Recording::query()->create(array_merge([
            'stream_id' => $stream->id,
            'source' => Recording::SOURCE_STUDIO,
            'is_public' => true,
            'title' => 'Podcast',
            'relative_path' => $rel,
            'completed_at' => now(),
        ], $overrides));
    }
}
