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

        Storage::disk('mediamtx_recordings')->put($recording->relative_path, 'fake-audio-bytes');

        $this->get(route('archive.play', $recording))
            ->assertOk()
            ->assertSee('Sunday Podcast', false)
            ->assertSee(route('archive.file', $recording), false)
            ->assertSee('data-duration-seconds="3195"', false)
            ->assertSee('53:15', false);
    }

    public function test_play_page_omits_file_url_when_recording_file_is_missing(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording(['title' => 'Missing File Podcast']);

        $this->get(route('archive.play', $recording))
            ->assertOk()
            ->assertSee('Missing File Podcast', false)
            ->assertDontSee(route('archive.file', $recording), false);
    }

    public function test_guest_can_stream_recording_file(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording();
        Storage::disk('mediamtx_recordings')->put($recording->relative_path, 'fake-audio-bytes');

        $this->get(route('archive.file', $recording))
            ->assertOk();

        $this->get(route('archive.file', $recording), ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Content-Range')
            ->assertHeader('Accept-Ranges', 'bytes');
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

    public function test_every_channel_archive_offers_publish_and_edit_to_its_manager(): void
    {
        Storage::fake('mediamtx_recordings');
        $this->fakeAudioConcatenator();

        $draft = $this->makeRecording([
            'title' => 'Sermon draft',
            'is_public' => false,
            'source' => Recording::SOURCE_ASSEMBLED,
            'duration_raw' => '3600',
        ]);
        Storage::disk('mediamtx_recordings')->put($draft->relative_path, str_repeat('a', 800_000));
        $org = $draft->stream->organization;
        $manager = \App\Models\User::factory()->create();
        $org->users()->attach($manager->id, ['role' => \App\Enums\OrgRole::Owner->value]);

        $this->get(route('archive.channel', $org))
            ->assertOk()
            ->assertDontSee('Sermon draft', false);

        $this->actingAs($manager)
            ->get(route('archive.channel', $org))
            ->assertOk()
            ->assertSee('Sermon draft', false)
            ->assertSee('Publish podcast', false)
            ->assertSee('Edit', false)
            ->assertSee(route('archive.edit', $draft), false);

        $this->actingAs($manager)
            ->get(route('archive.edit', $draft))
            ->assertOk()
            ->assertSee('Preview this cut', false)
            ->assertSee('Zoom to selection', false)
            ->assertSee('Keeping', false)
            ->assertDontSee('Use playhead', false)
            ->assertDontSee('Removed from the start', false)
            ->assertSee('name="start_seconds"', false)
            ->assertDontSee('Set start here', false)
            ->assertDontSee('type="number"', false);

        $this->actingAs($manager)
            ->post(route('archive.publish', $draft), ['published' => '1'])
            ->assertRedirect();

        $this->assertTrue($draft->fresh()->is_public);

        $this->actingAs($manager)
            ->post(route('archive.trim', $draft), [
                'start_seconds' => 600,
                'end_seconds' => 2400,
            ])
            ->assertRedirect(route('archive.edit', $draft));

        $draft->refresh();
        $this->assertSame('1800', $draft->duration_raw);
        $this->assertEquals(600.0, (float) $draft->trim_start_seconds);
        $this->assertNotNull($draft->master_path);
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($draft->master_path));
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($draft->relative_path));
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

    public function test_eligible_owner_can_publish_and_unpublish_archive_without_402(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording(['is_public' => false, 'title' => 'Service Broadcast']);
        $org = $recording->stream->organization;
        $owner = \App\Models\User::factory()->create();
        $org->users()->attach($owner->id, ['role' => \App\Enums\OrgRole::Owner->value]);

        // Publish
        $this->actingAs($owner)
            ->from(route('archive.channel', $org))
            ->post(route('archive.publish', $recording), ['published' => '1'])
            ->assertRedirect(route('archive.channel', $org))
            ->assertSessionHas('status', 'Podcast published.');

        $this->assertTrue($recording->fresh()->is_public);

        // Unpublish
        $this->actingAs($owner)
            ->from(route('archive.channel', $org))
            ->post(route('archive.publish', $recording), ['published' => '0'])
            ->assertRedirect(route('archive.channel', $org))
            ->assertSessionHas('status', 'Podcast unpublished. The recording stays saved.');

        $this->assertFalse($recording->fresh()->is_public);
    }

    public function test_unrelated_user_cannot_publish_archive(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording(['is_public' => false]);
        $stranger = \App\Models\User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('archive.publish', $recording), ['published' => '1'])
            ->assertForbidden();

        $this->assertFalse($recording->fresh()->is_public);
    }

    public function test_archive_publish_succeeds_even_when_recordings_count_exceeds_legacy_plan_quota(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording(['is_public' => false, 'title' => 'Archive 1501']);
        $org = $recording->stream->organization;

        // Plan with a low max_recordings limit
        $plan = \App\Models\Plan::query()->create([
            'name' => 'Starter Low',
            'slug' => 'starter-low-'.uniqid(),
            'amount' => 5000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['max_recordings' => 2, 'storage_bytes' => 1024 * 1024 * 1024],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        \App\Models\Subscription::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        // Existing published recordings already exceed the limit
        for ($i = 0; $i < 5; $i++) {
            Recording::query()->create([
                'stream_id' => $recording->stream_id,
                'source' => Recording::SOURCE_ASSEMBLED,
                'is_public' => true,
                'title' => 'Prior Broadcast '.$i,
                'relative_path' => $recording->stream->mediaPath().'/prior_'.$i.'.mp4',
                'duration_raw' => '1800',
                'size_bytes' => 20_000_000,
                'completed_at' => now(),
            ]);
        }

        $owner = \App\Models\User::factory()->create();
        $org->users()->attach($owner->id, ['role' => \App\Enums\OrgRole::Owner->value]);

        // Publishing must not return 402 Payment Required
        $response = $this->actingAs($owner)
            ->from(route('archive.channel', $org))
            ->post(route('archive.publish', $recording), ['published' => '1']);

        $response->assertRedirect(route('archive.channel', $org));
        $this->assertNotSame(402, $response->getStatusCode());
        $this->assertTrue($recording->fresh()->is_public);
    }

    public function test_repeated_publish_is_idempotent_and_safe(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording(['is_public' => true]);
        $org = $recording->stream->organization;
        $owner = \App\Models\User::factory()->create();
        $org->users()->attach($owner->id, ['role' => \App\Enums\OrgRole::Owner->value]);

        $this->actingAs($owner)
            ->post(route('archive.publish', $recording), ['published' => '1'])
            ->assertRedirect();

        $this->assertTrue($recording->fresh()->is_public);
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
