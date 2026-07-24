<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Recording;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StudioRecordingUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_studio_can_upload_local_recording_to_public_archive(): void
    {
        Storage::fake('mediamtx_recordings');

        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);

        $url = URL::temporarySignedRoute(
            'recordings.store',
            now()->addHour(),
            ['stream' => $stream],
        );

        $file = UploadedFile::fake()->create('session.webm', 240, 'audio/webm');

        $this->post($url, [
            'audio' => $file,
            'duration_seconds' => 125,
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJsonPath('ok', true);

        $recording = Recording::query()->first();
        $this->assertNotNull($recording);
        $this->assertSame(Recording::SOURCE_STUDIO, $recording->source);
        $this->assertTrue($recording->is_public);
        $this->assertSame('125', $recording->duration_raw);
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($recording->relative_path));

        $this->get(route('archive.index'))
            ->assertOk()
            ->assertSee('Church', false);
    }

    public function test_studio_upload_hides_mediamtx_copy_for_same_event(): void
    {
        Storage::fake('mediamtx_recordings');

        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Service',
            'status' => EventStatus::Ended,
            'ended_at' => now(),
        ]);
        $auto = Recording::query()->create([
            'stream_id' => $stream->id,
            'event_id' => $event->id,
            'title' => 'Service',
            'source' => Recording::SOURCE_MEDIAMTX,
            'is_public' => true,
            'relative_path' => $stream->mediaPath().'/auto.mp4',
            'completed_at' => now(),
        ]);

        $url = URL::temporarySignedRoute(
            'recordings.store',
            now()->addHour(),
            ['stream' => $stream],
        );

        $this->post($url, [
            'audio' => UploadedFile::fake()->create('session.webm', 240, 'audio/webm'),
            'event_id' => $event->id,
            'duration_seconds' => 90,
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->assertFalse($auto->fresh()->is_public);
        $this->assertTrue(Recording::query()->where('source', Recording::SOURCE_STUDIO)->first()->is_public);
    }

    public function test_upload_requires_valid_signature(): void
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);

        $this->post(route('recordings.store', $stream), [
            'audio' => UploadedFile::fake()->create('session.webm', 100, 'audio/webm'),
        ], [
            'Accept' => 'application/json',
        ])->assertForbidden();
    }
}
