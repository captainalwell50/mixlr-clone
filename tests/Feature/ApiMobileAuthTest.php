<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiMobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'host@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'host@example.com',
            'password' => 'password',
            'device_name' => 'pixel',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'onboarded']]);

        $this->assertNotEmpty($user->tokens()->first()?->token);
    }

    public function test_creator_home_and_publish_require_auth(): void
    {
        $stream = $this->makeManagedStream();

        $this->getJson('/api/v1/creator/home')->assertUnauthorized();
        $this->getJson('/api/v1/streams/'.$stream->uuid.'/publish')->assertUnauthorized();
    }

    public function test_creator_can_fetch_publish_credentials(): void
    {
        $stream = $this->makeManagedStream();
        $user = User::factory()->create();
        $stream->organization->users()->attach($user->id, ['role' => 'owner']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/creator/home')
            ->assertOk()
            ->assertJsonPath('onboarded', true)
            ->assertJsonPath('stream.uuid', $stream->uuid);

        $this->getJson('/api/v1/streams/'.$stream->uuid.'/publish')
            ->assertOk()
            ->assertJsonStructure(['whip_url', 'hls_url', 'whep_url', 'stream']);
    }

    public function test_discover_and_listen_are_public(): void
    {
        $stream = $this->makeManagedStream();
        $stream->forceFill([
            'is_public' => true,
            'status' => StreamStatus::Live,
            'started_at' => now(),
        ])->save();
        $stream->organization->forceFill(['is_public' => true])->save();

        $this->getJson('/api/v1/discover')
            ->assertOk()
            ->assertJsonCount(1, 'streams')
            ->assertJsonStructure([
                'streams' => [
                    [
                        'uuid',
                        'title',
                        'organization',
                        'logo_url',
                        'artwork_url',
                    ],
                ],
            ]);

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.uuid', $stream->uuid)
            ->assertJsonStructure(['stream' => ['hls_url', 'whep_url', 'playback_mode', 'prefer_hls'], 'organization'])
            ->assertJsonPath('stream.playback_mode', 'whep')
            ->assertJsonPath('stream.prefer_hls', false);

        config([
            'streaming.listen.prefer_hls' => true,
            'streaming.mediamtx.hls_cdn_base' => 'https://cdn.example.org/hls',
        ]);

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.playback_mode', 'hls')
            ->assertJsonPath('stream.prefer_hls', true)
            ->assertJsonPath('stream.hls_url', 'https://cdn.example.org/hls/'.$stream->mediaPath().'/index.m3u8');
    }

    public function test_user_can_update_profile_password_and_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Old Name',
            'password' => 'OldPass1!xx',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'OldPass1!xx',
            'password' => 'NewPass2!yy',
            'password_confirmation' => 'NewPass2!yy',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(Hash::check('NewPass2!yy', $user->fresh()->password));

        $file = UploadedFile::fake()->image('avatar.jpg', 240, 240);
        $this->post('/api/v1/auth/avatar', ['avatar' => $file], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonStructure(['user' => ['avatar_url']]);

        $this->assertNotNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
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
