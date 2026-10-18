<?php

namespace Tests;

use App\Contracts\AudioSegmentConcatenator;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeAudioConcatenator;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\SiteSetting::clearRuntimeCache();
        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function tearDown(): void
    {
        \App\Models\SiteSetting::clearRuntimeCache();
        \Illuminate\Support\Facades\Cache::flush();
        parent::tearDown();
    }
    protected function fakeAudioConcatenator(bool $shrink = false): void
    {
        $this->app->instance(AudioSegmentConcatenator::class, new FakeAudioConcatenator(shrink: $shrink));
    }

    protected function grantFreeBroadcast(Organization $organization, ?\DateTimeInterface $until = null): Subscription
    {
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'amount' => 0,
                'currency' => 'NGN',
                'interval' => 'monthly',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        return Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => $until ?? now()->addDays(30),
        ]);
    }
}
