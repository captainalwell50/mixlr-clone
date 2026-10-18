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
            ->assertSee('studio-root', false)
            ->assertSee('value="auto" selected', false)
            ->assertSee('data-studio-reload-at', false);
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

    public function test_studio_defaults_to_light_theme_and_renders_theme_toggle_and_polish_elements(): void
    {
        $stream = $this->makeStream();
        $url = URL::temporarySignedRoute(
            'studio.stream',
            now()->addHour(),
            ['stream' => $stream]
        );

        $response = $this->get($url)->assertOk();

        // Light mode is default on the Studio stage and in the inline bootstrap script
        $response->assertSee('id="studio-stage"', false)
            ->assertSee('data-studio-theme="light"', false)
            ->assertSee("var key = 'soundmix-studio-theme';", false)
            ->assertSee("var theme = stored === 'dark' ? 'dark' : 'light';", false)
            ->assertSee('id="studio-theme-toggle"', false)
            ->assertSee('data-studio-theme-btn="light"', false)
            ->assertSee('data-studio-theme-btn="dark"', false);

        // Microphone setup banner is hidden by default until needed
        $response->assertSee('<div id="mic-enable-wrap" class="mixer-mic-enable" hidden>', false);

        // Organisation artwork fallback initial renders when no artwork URL exists
        $response->assertSee('mixer-topbar-art is-fallback', false)
            ->assertSee('<span class="mixer-topbar-art-fallback">C</span>', false);

        // Redundant CH 1 / CH 2 / CH 3 labels are removed while primary strip identities remain
        $response->assertSee('INPUT 1', false)
            ->assertSee('INPUT 2', false)
            ->assertSee('PLAYLIST', false)
            ->assertSee('MASTER', false)
            ->assertDontSee('>CH 1<', false)
            ->assertDontSee('>CH 2<', false)
            ->assertDontSee('>CH 3<', false);

        // Stage source guidance pills render cleanly
        $response->assertSee('EasyWorship', false)
            ->assertSee('vMix', false)
            ->assertSee('OBS / NDI', false);

        // Share tab hides ACTIVE EVENT LINK block when no open event exists so the channel URL is not duplicated
        $response->assertSee('id="share-event-block"  hidden', false)
            ->assertSee('PERMANENT CHANNEL LINK', false);
    }

    private function makeStream(): Stream
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
        ]);
        $this->grantFreeBroadcast($org);

        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);
    }
}
