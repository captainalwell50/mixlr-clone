<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_public_live_events(): void
    {
        $org = Organization::query()->create(['name' => 'Grace', 'slug' => 'grace', 'is_public' => true]);
        Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Morning Live',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);
        Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Unlisted Show',
            'status' => EventStatus::Live,
            'access' => EventAccess::Unlisted,
            'started_at' => now(),
        ]);

        $this->get(route('discover'))
            ->assertOk()
            ->assertSee('Morning Live', false)
            ->assertDontSee('Unlisted Show', false);
    }

    public function test_search_filters_by_title(): void
    {
        $org = Organization::query()->create(['name' => 'Grace', 'slug' => 'grace-2', 'is_public' => true]);
        Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Alpha Service',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);

        $this->get(route('discover', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Service', false);

        $this->get(route('discover', ['q' => 'ZZZ']))
            ->assertOk()
            ->assertDontSee('Alpha Service', false);
    }
}
