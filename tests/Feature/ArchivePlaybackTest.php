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
        $recording = $this->makeRecording();

        $this->get(route('archive.play', $recording))
            ->assertOk()
            ->assertSee($recording->stream->title, false)
            ->assertSee(route('archive.file', $recording), false);
    }

    public function test_guest_can_stream_recording_file(): void
    {
        Storage::fake('mediamtx_recordings');
        $recording = $this->makeRecording();
        Storage::disk('mediamtx_recordings')->put($recording->relative_path, 'fake-audio-bytes');

        $this->get(route('archive.file', $recording))
            ->assertOk();
    }

    public function test_archive_index_has_friendly_empty_state(): void
    {
        $this->get(route('archive.index'))
            ->assertOk()
            ->assertSee('No recordings yet', false)
            ->assertDontSee('mediamtx-recordings', false);
    }

    private function makeRecording(): Recording
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
        $rel = $stream->mediaPath().'/part1.mp4';

        return Recording::query()->create([
            'stream_id' => $stream->id,
            'relative_path' => $rel,
            'completed_at' => now(),
        ]);
    }
}
