<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListenSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_listen_prefer_hls_and_listen_api_reflects_it(): void
    {
        $admin = User::factory()->admin()->create();
        $stream = $this->makePublicLiveStream();

        config([
            'streaming.listen.prefer_hls' => false,
            'streaming.listen.hls_aac_sidecar' => false,
            'streaming.mediamtx.hls_cdn_base' => 'https://cdn.example.org/hls',
        ]);

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.prefer_hls', false)
            ->assertJsonPath('stream.playback_mode', 'whep');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'listen_prefer_hls' => 'hls',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('hls', SiteSetting::listenPreferHlsChoice());
        $this->assertTrue(SiteSetting::listenPreferHls());

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.prefer_hls', true)
            ->assertJsonPath('stream.playback_mode', 'hls')
            ->assertJsonPath('stream.hls_url', 'https://cdn.example.org/hls/'.$stream->mediaPath().'/index.m3u8');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'listen_prefer_hls' => 'whep',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.prefer_hls', false)
            ->assertJsonPath('stream.playback_mode', 'whep');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'listen_prefer_hls' => 'auto',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        // Auto + CDN base ⇒ prefer HLS even when .env says false
        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.prefer_hls', true)
            ->assertJsonPath('stream.playback_mode', 'hls');
    }

    public function test_non_admin_cannot_update_listen_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'C', 'slug' => 'c-'.uniqid()]);
        $org->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'listen_prefer_hls' => 'hls',
            ])
            ->assertForbidden();

        $this->assertNull(SiteSetting::listenPreferHlsChoice());
    }

    public function test_admin_settings_page_shows_help_and_effective_mode(): void
    {
        $admin = User::factory()->admin()->create();

        SiteSetting::putValue(SiteSetting::KEY_LISTEN_PREFER_HLS, 'hls');
        config([
            'streaming.mediamtx.hls_cdn_base' => 'https://cdn.example.org/hls',
            'streaming.listen.hls_aac_sidecar' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Effective mode now', false)
            ->assertSee('CDN HLS', false)
            ->assertSee('Forced CDN HLS', false)
            ->assertSee('cdn.example.org', false)
            ->assertSee('AAC sidecar', false)
            ->assertSee('data-prefer-hls', false)
            ->assertSee('index.m3u8', false)
            ->assertSee('Prefer CDN HLS', false)
            ->assertSee('data-effective-mode="hls"', false);
    }

    public function test_admin_settings_page_explains_auto_resolution(): void
    {
        $admin = User::factory()->admin()->create();

        SiteSetting::putValue(SiteSetting::KEY_LISTEN_PREFER_HLS, 'auto');
        config([
            'streaming.listen.prefer_hls' => null,
            'streaming.listen.hls_aac_sidecar' => true,
            'streaming.mediamtx.hls_cdn_base' => 'https://cdn.example.org/hls',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Effective mode now', false)
            ->assertSee('CDN HLS', false)
            ->assertSee('Auto → HLS because CDN base + AAC sidecar', false)
            ->assertSee('Admin override (auto)', false);
    }

    public function test_env_fallback_when_admin_setting_unset(): void
    {
        config([
            'streaming.listen.prefer_hls' => true,
            'streaming.listen.hls_aac_sidecar' => false,
            'streaming.mediamtx.hls_cdn_base' => null,
        ]);

        $stream = $this->makePublicLiveStream();

        $this->assertNull(SiteSetting::listenPreferHlsChoice());
        $this->assertTrue($stream->preferHlsListen());
        $this->assertSame('hls', $stream->playbackMode());
    }

    private function makePublicLiveStream(): Stream
    {
        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-'.uniqid(),
            'is_public' => true,
        ]);

        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Live',
            'status' => StreamStatus::Live,
            'is_public' => true,
            'started_at' => now(),
        ]);
    }
}
