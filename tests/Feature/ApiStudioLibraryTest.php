<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\StudioAudioAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiStudioLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_list_upload_and_delete_library_via_api(): void
    {
        Storage::fake('public');

        $stream = $this->makeManagedStream();
        $user = User::factory()->create();
        $stream->organization->users()->attach($user->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('intro.mp3', 240, 'audio/mpeg');

        $this->post('/api/v1/streams/'.$stream->uuid.'/library', [
            'audio' => $file,
            'title' => 'Intro Bed',
        ])->assertCreated()
            ->assertJsonPath('asset.title', 'Intro Bed');

        $this->getJson('/api/v1/streams/'.$stream->uuid.'/library')
            ->assertOk()
            ->assertJsonCount(1, 'assets');

        $asset = StudioAudioAsset::query()->firstOrFail();

        $this->deleteJson('/api/v1/streams/'.$stream->uuid.'/library/'.$asset->id)
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_desktop_mixer_embed_url_requires_auth(): void
    {
        $stream = $this->makeManagedStream();

        $this->getJson('/api/v1/streams/'.$stream->uuid.'/desktop-mixer')
            ->assertUnauthorized();

        $user = User::factory()->create();
        $stream->organization->users()->attach($user->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/streams/'.$stream->uuid.'/desktop-mixer')
            ->assertOk()
            ->assertJsonStructure(['embed_url', 'whip_url', 'stream']);
    }

    private function makeManagedStream(): Stream
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
            'is_public' => true,
        ]);

        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
            'is_public' => true,
        ]);
    }
}
