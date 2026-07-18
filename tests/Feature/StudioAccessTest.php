<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StudioAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_studio_url_is_rejected(): void
    {
        $stream = $this->makeStream();

        $this->get('/studio/'.$stream->uuid)
            ->assertForbidden();
    }

    public function test_signed_studio_url_works(): void
    {
        $stream = $this->makeStream();
        $url = URL::temporarySignedRoute(
            'studio.stream',
            now()->addHour(),
            ['stream' => $stream]
        );

        $this->get($url)
            ->assertOk()
            ->assertSee('Go live', false)
            ->assertSee('studio-root', false);
    }

    public function test_admin_can_open_studio_without_signed_url(): void
    {
        $stream = $this->makeStream();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.streams.studio', $stream))
            ->assertOk()
            ->assertSee('Go live', false);
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
