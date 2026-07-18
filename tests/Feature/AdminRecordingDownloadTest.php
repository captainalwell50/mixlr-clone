<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Recording;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRecordingDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_recording_file(): void
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
        $rel = $stream->mediaPath().'/part1.mp4';
        Storage::disk('mediamtx_recordings')->put($rel, 'fake-audio');
        $recording = Recording::query()->create([
            'stream_id' => $stream->id,
            'relative_path' => $rel,
            'completed_at' => now(),
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.recordings.download', $recording));

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('content-disposition'));
    }

    public function test_guest_is_redirected_from_download(): void
    {
        Storage::fake('mediamtx_recordings');

        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church2-'.uniqid(),
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);
        $recording = Recording::query()->create([
            'stream_id' => $stream->id,
            'relative_path' => 'x.bin',
            'completed_at' => now(),
        ]);

        $this->get(route('admin.recordings.download', $recording))
            ->assertRedirect(route('login'));
    }
}
