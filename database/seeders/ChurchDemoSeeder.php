<?php

namespace Database\Seeders;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use App\Services\EventBroadcastService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\URL;

class ChurchDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->firstOrCreate(
            ['slug' => 'demo-church'],
            [
                'name' => 'Demo Church',
                'tagline' => 'Sunday services and midweek prayer',
                'theme_color' => '#3d9b7a',
                'is_public' => true,
                'branding_config' => ['accent' => '#3d9b7a'],
            ]
        );

        $admin = User::query()->where('is_admin', true)->first();
        if ($admin !== null) {
            $org->users()->syncWithoutDetaching([
                $admin->id => ['role' => OrgRole::Owner->value],
            ]);
        }

        $stream = Stream::query()->firstOrCreate(
            ['uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            [
                'organization_id' => $org->id,
                'title' => 'Sunday service',
                'description' => 'Demo public stream',
                'is_public' => true,
                'chat_enabled' => true,
            ]
        );

        if (empty($stream->stream_key)) {
            $stream->regenerateStreamKey();
        }

        $event = Event::query()->firstOrCreate(
            ['uuid' => '11111111-2222-3333-4444-555555555555'],
            [
                'organization_id' => $org->id,
                'stream_id' => $stream->id,
                'title' => 'Sunday service',
                'description' => 'Demo event — share this link; it becomes the live page.',
                'scheduled_at' => now()->next('Sunday')->setTime(10, 0),
                'status' => EventStatus::Scheduled,
                'access' => EventAccess::Public,
                'chat_enabled' => true,
                'show_listener_count' => true,
            ]
        );

        app(EventBroadcastService::class)->ensureStream($event);

        $studioUrl = URL::temporarySignedRoute(
            'studio.stream',
            now()->addMonths(6),
            ['stream' => $stream]
        );

        $this->command->info('Channel: '.route('channels.show', $org));
        $this->command->info('Event:   '.route('events.show', $event));
        $this->command->info('Discover: '.route('discover'));
        $this->command->warn('Studio (signed): '.$studioUrl);
        $this->command->warn('OBS key: '.$stream->rtmpStreamKeyForObs());
    }
}
