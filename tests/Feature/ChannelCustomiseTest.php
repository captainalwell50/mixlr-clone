<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChannelCustomiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_manager_can_view_customise_page(): void
    {
        [$org, $manager] = $this->orgWithManager();

        $this->actingAs($manager)
            ->get(route('admin.organizations.customise', $org))
            ->assertOk()
            ->assertSee('Customise channel')
            ->assertSee('Listen background')
            ->assertSee('Channel logo')
            ->assertSee('Artwork');
    }

    public function test_outsider_cannot_customise_channel(): void
    {
        [$org] = $this->orgWithManager();
        $other = Organization::query()->create(['name' => 'Other', 'slug' => 'other']);
        $outsider = User::factory()->create(['is_admin' => false]);
        $other->users()->attach($outsider->id, ['role' => OrgRole::Owner->value]);

        $this->actingAs($outsider)
            ->get(route('admin.organizations.customise', $org))
            ->assertForbidden();
    }

    public function test_manager_can_upload_logo_artwork_and_listen_background(): void
    {
        Storage::fake('public');

        [$org, $manager, $stream] = $this->orgWithManager(withStream: true);

        $this->actingAs($manager)
            ->post(route('admin.organizations.customise.logo', $org), [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertRedirect(route('admin.organizations.customise', $org));

        $org->refresh();
        $this->assertNotNull($org->logo_path);
        Storage::disk('public')->assertExists($org->logo_path);
        $this->assertStringContainsString('/storage/', (string) $org->logoUrl());

        $this->actingAs($manager)
            ->post(route('admin.organizations.customise.artwork', $org), [
                'artwork' => UploadedFile::fake()->image('art.jpg', 800, 450),
            ])
            ->assertRedirect(route('admin.organizations.customise', $org));

        $org->refresh();
        $this->assertNotNull($org->artwork_path);
        Storage::disk('public')->assertExists($org->artwork_path);

        $this->actingAs($manager)
            ->post(route('admin.organizations.customise.background', $org), [
                'background' => UploadedFile::fake()->image('bg.jpg', 1920, 1080),
            ])
            ->assertRedirect(route('admin.organizations.customise', $org));

        $stream->refresh();
        $this->assertNotNull($stream->listen_background_path);
        Storage::disk('public')->assertExists($stream->listen_background_path);
    }

    public function test_manager_can_remove_branding_assets(): void
    {
        Storage::fake('public');

        [$org, $manager, $stream] = $this->orgWithManager(withStream: true);

        $logo = UploadedFile::fake()->image('logo.png')->store('org-branding/'.$org->id.'/logo', 'public');
        $art = UploadedFile::fake()->image('art.jpg')->store('org-branding/'.$org->id.'/artwork', 'public');
        $bg = UploadedFile::fake()->image('bg.jpg')->store('listen-bg/'.$stream->uuid, 'public');

        $org->forceFill(['logo_path' => $logo, 'artwork_path' => $art])->save();
        $stream->forceFill(['listen_background_path' => $bg])->save();

        $this->actingAs($manager)
            ->delete(route('admin.organizations.customise.logo.destroy', $org))
            ->assertRedirect(route('admin.organizations.customise', $org));
        $this->assertNull($org->fresh()->logo_path);
        Storage::disk('public')->assertMissing($logo);

        $this->actingAs($manager)
            ->delete(route('admin.organizations.customise.artwork.destroy', $org))
            ->assertRedirect(route('admin.organizations.customise', $org));
        $this->assertNull($org->fresh()->artwork_path);

        $this->actingAs($manager)
            ->delete(route('admin.organizations.customise.background.destroy', $org))
            ->assertRedirect(route('admin.organizations.customise', $org));
        $this->assertNull($stream->fresh()->listen_background_path);
    }

    public function test_studio_no_longer_shows_listen_background_controls(): void
    {
        [$org, $manager, $stream] = $this->orgWithManager(withStream: true);

        $this->actingAs($manager)
            ->get(route('admin.streams.studio', $stream))
            ->assertOk()
            ->assertDontSee('Set background')
            ->assertDontSee('Full-screen image behind the listener page')
            ->assertSee('Customise channel');
    }

    /**
     * @return array{0: Organization, 1: User, 2?: Stream}
     */
    private function orgWithManager(bool $withStream = false): array
    {
        $org = Organization::query()->create(['name' => 'Brand Church', 'slug' => 'brand-church']);
        $manager = User::factory()->create(['is_admin' => false]);
        $org->users()->attach($manager->id, ['role' => OrgRole::Admin->value]);

        if (! $withStream) {
            return [$org, $manager];
        }

        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);

        return [$org, $manager, $stream];
    }
}
