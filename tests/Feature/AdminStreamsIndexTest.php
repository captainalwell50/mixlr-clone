<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStreamsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_streams_index_shows_account_created_date(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create([
            'name' => 'Sunrise Church',
            'slug' => 'sunrise-'.uniqid(),
            'created_at' => now()->subDays(3),
        ]);
        Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main mix',
            'status' => StreamStatus::Offline,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.streams.index'))
            ->assertOk()
            ->assertSee('Account created', false)
            ->assertSee(\App\Support\LocalTime::format($org->created_at, 'M j, Y'), false);
    }

    public function test_streams_search_filters_by_title_channel_and_uuid(): void
    {
        $admin = User::factory()->admin()->create();
        $orgA = Organization::query()->create(['name' => 'Grace Chapel', 'slug' => 'grace-'.uniqid()]);
        $orgB = Organization::query()->create(['name' => 'City Church', 'slug' => 'city-'.uniqid()]);

        $streamA = Stream::query()->create([
            'organization_id' => $orgA->id,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'title' => 'Alpha Morning Glory',
            'status' => StreamStatus::Offline,
        ]);
        $streamB = Stream::query()->create([
            'organization_id' => $orgB->id,
            'uuid' => '99999999-8888-7777-6666-555555555555',
            'title' => 'Omega Evening Service',
            'status' => StreamStatus::Live,
        ]);

        // Search by title
        $this->actingAs($admin)
            ->get(route('admin.streams.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Morning Glory')
            ->assertDontSee('Omega Evening Service');

        // Search by channel name
        $this->actingAs($admin)
            ->get(route('admin.streams.index', ['q' => 'City Church']))
            ->assertOk()
            ->assertSee('Omega Evening Service')
            ->assertDontSee('Alpha Morning Glory');

        // Search by uuid substring
        $this->actingAs($admin)
            ->get(route('admin.streams.index', ['q' => '99999999']))
            ->assertOk()
            ->assertSee('Omega Evening Service')
            ->assertDontSee('Alpha Morning Glory');
    }

    public function test_streams_status_filter_filters_live_and_offline(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'Faith Center', 'slug' => 'faith-'.uniqid()]);

        $liveStream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Live Sanctuary Feed',
            'status' => StreamStatus::Live,
            'started_at' => now()->subMinutes(25),
        ]);
        $offlineStream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Offline Youth Mix',
            'status' => StreamStatus::Offline,
        ]);

        // Filter live only
        $this->actingAs($admin)
            ->get(route('admin.streams.index', ['status' => 'live']))
            ->assertOk()
            ->assertSee('Live Sanctuary Feed')
            ->assertDontSee('Offline Youth Mix');

        // Filter offline only
        $this->actingAs($admin)
            ->get(route('admin.streams.index', ['status' => 'offline']))
            ->assertOk()
            ->assertSee('Offline Youth Mix')
            ->assertDontSee('Live Sanctuary Feed');
    }

    public function test_streams_organization_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $org1 = Organization::query()->create(['name' => 'First Baptist', 'slug' => 'first-'.uniqid()]);
        $org2 = Organization::query()->create(['name' => 'Second Baptist', 'slug' => 'second-'.uniqid()]);

        Stream::query()->create([
            'organization_id' => $org1->id,
            'uuid' => fake()->uuid(),
            'title' => 'Stream From Org One',
            'status' => StreamStatus::Offline,
        ]);
        Stream::query()->create([
            'organization_id' => $org2->id,
            'uuid' => fake()->uuid(),
            'title' => 'Stream From Org Two',
            'status' => StreamStatus::Offline,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.streams.index', ['organization_id' => $org1->id]))
            ->assertOk()
            ->assertSee('Stream From Org One')
            ->assertDontSee('Stream From Org Two');
    }

    public function test_streams_shows_prominent_live_indicator_and_inspect_as_primary_action(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'Praise Cathedral', 'slug' => 'praise-'.uniqid()]);

        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Praise and Worship Live',
            'status' => StreamStatus::Live,
            'started_at' => now()->subMinutes(40),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.streams.index'));

        $response->assertOk()
            ->assertSee('Praise and Worship Live')
            ->assertSee('LIVE')
            ->assertSee('Inspect')
            ->assertSee('Listen')
            ->assertSee('Studio')
            ->assertSee('Embed')
            ->assertSee(route('admin.streams.edit', $stream));
    }
}
