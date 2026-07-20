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
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StudioAudioLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_upload_list_search_and_delete(): void
    {
        Storage::fake('public');

        $stream = $this->makeStream();
        $uploadUrl = URL::temporarySignedRoute(
            'studio.library.store',
            now()->addHour(),
            ['stream' => $stream],
        );
        $listUrl = URL::temporarySignedRoute(
            'studio.library.index',
            now()->addHour(),
            ['stream' => $stream],
        );

        $file = UploadedFile::fake()->create('sunday-worship.mp3', 320, 'audio/mpeg');

        $this->post($uploadUrl, [
            'audio' => $file,
            'title' => 'Sunday Worship',
            'duration_seconds' => 215,
        ])->assertCreated()
            ->assertJsonPath('asset.title', 'Sunday Worship');

        $this->assertDatabaseCount('studio_audio_assets', 1);
        Storage::disk('public')->assertExists(
            StudioAudioAsset::query()->firstOrFail()->path
        );

        $this->getJson($listUrl)
            ->assertOk()
            ->assertJsonCount(1, 'assets')
            ->assertJsonPath('assets.0.title', 'Sunday Worship');

        $asset = StudioAudioAsset::query()->firstOrFail();
        $deleteUrl = URL::temporarySignedRoute(
            'studio.library.destroy',
            now()->addHour(),
            ['stream' => $stream, 'asset' => $asset],
        );

        $this->deleteJson($deleteUrl)->assertOk();
        $this->assertDatabaseCount('studio_audio_assets', 0);
    }

    public function test_unsigned_library_is_forbidden(): void
    {
        $stream = $this->makeStream();

        $this->getJson(route('studio.library.index', $stream))->assertForbidden();
        $this->postJson(route('studio.library.store', $stream))->assertForbidden();
    }

    public function test_org_admin_can_manage_library_without_signature(): void
    {
        Storage::fake('public');

        $stream = $this->makeStream();
        $admin = User::factory()->create();
        $stream->organization->users()->attach($admin->id, ['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('studio.library.store', $stream), [
                'audio' => UploadedFile::fake()->create('intro.mp3', 120, 'audio/mpeg'),
                'title' => 'Intro',
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->getJson(route('studio.library.index', $stream).'?q=Intro')
            ->assertOk()
            ->assertJsonCount(1, 'assets');

        $this->actingAs($admin)
            ->getJson(route('studio.library.index', $stream).'?q=jazz')
            ->assertOk()
            ->assertJsonCount(0, 'assets');
    }

    private function makeStream(): Stream
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
        ]);

        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);
    }
}
