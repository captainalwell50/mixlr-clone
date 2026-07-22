<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\StudioAudioAsset;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorageQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_drive_assets_do_not_count_against_quota(): void
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-quota',
            'is_public' => true,
        ]);

        $plan = Plan::query()->create([
            'name' => 'Free',
            'slug' => 'free-test',
            'amount' => 0,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['storage_bytes' => 1000],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Subscription::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $stream = $org->streams()->create([
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => 'offline',
            'is_public' => true,
        ]);

        StudioAudioAsset::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Local',
            'original_filename' => 'a.mp3',
            'path' => 'studio-audio/a.mp3',
            'storage_provider' => 'local',
            'size_bytes' => 400,
        ]);

        StudioAudioAsset::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Drive',
            'original_filename' => 'b.mp3',
            'path' => 'drive:abc',
            'storage_provider' => 'drive',
            'external_id' => 'abc',
            'size_bytes' => 50_000,
        ]);

        $quota = app(StorageQuotaService::class);

        $this->assertSame(400, $quota->usedBytes($org));
        $quota->assertCanStore($org, 600);

        $this->expectException(ValidationException::class);
        $quota->assertCanStore($org, 601);
    }
}
