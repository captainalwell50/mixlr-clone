<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonCount(1, 'streams');

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.uuid', $stream->uuid)
            ->assertJsonStructure(['stream' => ['hls_url', 'whep_url'], 'organization']);
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
