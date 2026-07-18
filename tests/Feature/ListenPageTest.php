<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListenPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_listen_redirects_to_linked_event(): void
    {
        $org = Organization::query()->create(['name' => 'G', 'slug' => 'g', 'is_public' => true]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Live,
        ]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Morning Service',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);

        $this->get(route('listen.stream', $stream))
            ->assertRedirect(route('events.show', $event));
    }

    public function test_event_page_shows_live_badge(): void
    {
        $org = Organization::query()->create([
            'name' => 'Grace Church',
            'slug' => 'grace-'.uniqid(),
            'is_public' => true,
            'theme_color' => '#3d9b7a',
        ]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Morning Service',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
            'show_listener_count' => true,
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Live', false)
            ->assertSee('Morning Service', false);
    }

    public function test_embed_includes_status(): void
    {
        $org = Organization::query()->create(['name' => 'G', 'slug' => 'g2', 'is_public' => true]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Embed Me',
            'status' => EventStatus::Scheduled,
            'access' => EventAccess::Public,
        ]);

        $this->get(route('events.embed', $event))
            ->assertOk()
            ->assertSee('Offline', false);
    }
}
