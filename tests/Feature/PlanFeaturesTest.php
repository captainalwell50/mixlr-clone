<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PlanFeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_package_with_selected_features(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'Intermediate',
                'slug' => 'intermediate',
                'amount' => 380000,
                'currency' => 'NGN',
                'interval' => 'monthly',
                'sort_order' => 3,
                'is_active' => '1',
                'max_streams' => 2,
                'max_recordings' => 120,
                'storage_gb' => 25,
                'features' => [
                    'live_streaming' => '1',
                    'channel_page' => '1',
                    'donations' => '1',
                    'listener_analytics' => '1',
                ],
            ])
            ->assertRedirect(route('admin.plans.index'));

        $plan = Plan::query()->where('slug', 'intermediate')->first();
        $this->assertNotNull($plan);
        $this->assertTrue($plan->hasFeature('donations'));
        $this->assertTrue($plan->hasFeature('listener_analytics'));
        $this->assertFalse($plan->hasFeature('rtmp_ingest'));
        $this->assertSame(120, $plan->maxRecordings());
        $this->assertSame(25 * 1024 * 1024 * 1024, $plan->storageBytes());
    }

    public function test_organization_feature_follows_plan_limits(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Basic',
            'slug' => 'basic-'.uniqid(),
            'amount' => 1000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => array_merge(PlanFeatureCatalog::defaultLimits(), [
                'gallery' => false,
                'private_streams' => true,
            ]),
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $org = Organization::query()->create([
            'name' => 'Org',
            'slug' => 'org-'.uniqid(),
        ]);
        Subscription::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->assertFalse($org->fresh()->hasFeature('gallery'));
        $this->assertTrue($org->fresh()->hasFeature('private_streams'));
    }

    public function test_billing_page_lists_selected_feature_bullets(): void
    {
        $user = User::factory()->create();
        $org = Organization::query()->create(['name' => 'O', 'slug' => 'o-'.uniqid()]);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $plan = Plan::query()->create([
            'name' => 'Shown',
            'slug' => 'shown-'.uniqid(),
            'amount' => 5000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => array_merge(PlanFeatureCatalog::defaultLimits(), [
                'live_streaming' => true,
                'max_recordings' => 120,
                'donations' => true,
            ]),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('120 podcasts or uploads', false)
            ->assertSee('Donations / support link', false)
            ->assertSee($plan->name, false);
    }
}
