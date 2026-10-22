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

        $created = $this->post($uploadUrl, [
            'audio' => $file,
            'title' => 'Sunday Worship',
            'duration_seconds' => 215,
            'destination' => 'local',
        ])->assertCreated()
            ->assertJsonPath('asset.title', 'Sunday Worship')
            ->assertJsonPath('stored_as', 'local');

        $this->assertDatabaseCount('studio_audio_assets', 1);
        Storage::disk('public')->assertExists(
            StudioAudioAsset::query()->firstOrFail()->path
        );

        $playbackUrl = $created->json('asset.url');
        $this->assertIsString($playbackUrl);
        $this->assertStringContainsString('/library/', $playbackUrl);
        $this->assertStringContainsString('/file', $playbackUrl);
        $this->get($playbackUrl)->assertOk();

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

    public function test_drive_destination_requires_connection(): void
    {
        Storage::fake('public');

        $stream = $this->makeStream();
        $admin = User::factory()->create();
        $stream->organization->users()->attach($admin->id, ['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson(route('studio.library.store', $stream), [
                'audio' => UploadedFile::fake()->create('intro.mp3', 120, 'audio/mpeg'),
                'destination' => 'drive',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination');
    }

    public function test_platform_falls_back_to_server_disk_when_object_storage_is_off(): void
    {
        Storage::fake('public');
        config(['object_storage.enabled' => false]);

        $stream = $this->makeStream();
        $uploadUrl = URL::temporarySignedRoute(
            'studio.library.store',
            now()->addHour(),
            ['stream' => $stream],
        );

        $this->post($uploadUrl, [
            'audio' => UploadedFile::fake()->create('fallback.mp3', 80, 'audio/mpeg'),
            'destination' => 'platform',
        ])->assertCreated()
            ->assertJsonPath('stored_as', 'local')
            ->assertJsonPath(
                'notice',
                'Platform cloud storage is not enabled, so this file was saved on the server disk.',
            );
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

    public function test_full_library_file_advertises_byte_ranges(): void
    {
        [$url, $payload] = $this->rangeFile();

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Length', (string) strlen($payload));
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertSame($payload, $response->streamedContent());
    }

    public function test_library_file_serves_a_beginning_range(): void
    {
        [$url, $payload] = $this->rangeFile();

        $response = $this->call('GET', $url, [], [], [], ['HTTP_RANGE' => 'bytes=0-1']);

        $response->assertStatus(206);
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Length', '2');
        $response->assertHeader('Content-Range', 'bytes 0-1/'.strlen($payload));
        $this->assertSame(substr($payload, 0, 2), $response->streamedContent());
    }

    public function test_library_file_serves_a_middle_range(): void
    {
        [$url, $payload] = $this->rangeFile();

        $response = $this->call('GET', $url, [], [], [], ['HTTP_RANGE' => 'bytes=100-199']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Length', '100');
        $response->assertHeader('Content-Range', 'bytes 100-199/'.strlen($payload));
        $this->assertSame(substr($payload, 100, 100), $response->streamedContent());
    }

    public function test_library_file_serves_an_open_ended_range(): void
    {
        [$url, $payload] = $this->rangeFile();

        $response = $this->call('GET', $url, [], [], [], ['HTTP_RANGE' => 'bytes=100-']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Length', (string) (strlen($payload) - 100));
        $response->assertHeader('Content-Range', 'bytes 100-'.(strlen($payload) - 1).'/'.strlen($payload));
        $this->assertSame(substr($payload, 100), $response->streamedContent());
    }

    public function test_library_file_serves_a_suffix_range(): void
    {
        [$url, $payload] = $this->rangeFile();
        $size = strlen($payload);

        $response = $this->call('GET', $url, [], [], [], ['HTTP_RANGE' => 'bytes=-100']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Length', '100');
        $response->assertHeader('Content-Range', 'bytes '.($size - 100).'-'.($size - 1).'/'.$size);
        $this->assertSame(substr($payload, -100), $response->streamedContent());
    }

    public function test_library_file_rejects_an_unsatisfiable_range(): void
    {
        [$url, $payload] = $this->rangeFile();

        $response = $this->call('GET', $url, [], [], [], ['HTTP_RANGE' => 'bytes=99999-99999']);

        $response->assertStatus(416);
        $response->assertHeader('Content-Range', 'bytes */'.strlen($payload));
        $this->assertSame('', $response->getContent());
    }

    public function test_library_file_head_has_range_headers_and_no_body(): void
    {
        [$url, $payload] = $this->rangeFile();

        $response = $this->call('HEAD', $url);

        $response->assertOk();
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $response->assertHeader('Content-Length', (string) strlen($payload));
        $this->assertSame('', $response->getContent());
    }

    public function test_invalid_library_signature_does_not_return_the_file(): void
    {
        [$url, $payload] = $this->rangeFile();
        $tampered = preg_replace('/signature=[^&]+/', 'signature=deadbeef', $url);

        $this->get($tampered)->assertForbidden();
        $this->assertStringNotContainsString($payload, $this->get($tampered)->getContent());
    }

    public function test_expired_library_signature_does_not_return_the_file(): void
    {
        Storage::fake('public');
        $stream = $this->makeStream();
        $payload = 'expired-library-bytes';
        $path = 'studio-audio/'.$stream->uuid.'/expired.bin';
        Storage::disk('public')->put($path, $payload);
        $asset = StudioAudioAsset::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Expired',
            'original_filename' => 'expired.mp3',
            'path' => $path,
            'storage_provider' => StudioAudioAsset::PROVIDER_LOCAL,
            'mime_type' => 'audio/mpeg',
            'size_bytes' => strlen($payload),
        ]);
        $url = URL::temporarySignedRoute(
            'studio.library.file',
            now()->subMinute(),
            ['stream' => $stream, 'asset' => $asset],
        );

        $response = $this->get($url);

        $response->assertForbidden();
        $this->assertStringNotContainsString($payload, $response->getContent());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rangeFile(): array
    {
        Storage::fake('public');
        $stream = $this->makeStream();
        $payload = '';
        for ($i = 0; $i < 400; $i++) {
            $payload .= chr($i % 256);
        }
        $path = 'studio-audio/'.$stream->uuid.'/range.bin';
        Storage::disk('public')->put($path, $payload);
        $asset = StudioAudioAsset::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Range',
            'original_filename' => 'range.mp3',
            'path' => $path,
            'storage_provider' => StudioAudioAsset::PROVIDER_LOCAL,
            'mime_type' => 'audio/mpeg',
            'size_bytes' => strlen($payload),
        ]);

        return [
            URL::temporarySignedRoute(
                'studio.library.file',
                now()->addHour(),
                ['stream' => $stream, 'asset' => $asset],
            ),
            $payload,
        ];
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
