<?php

namespace Tests\Feature;

use App\Enums\CreatorType;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Stream;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_redirects_to_onboarding_when_enabled(): void
    {
        config(['app.registration_enabled' => true]);

        $this->post('/register', [
            'name' => 'Creator',
            'email' => 'creator@example.org',
            'password' => 'Password1!xx',
            'password_confirmation' => 'Password1!xx',
        ])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticated();
    }

    public function test_onboarding_creates_org_owner_and_default_stream(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('onboarding.type'), ['creator_type' => CreatorType::Church->value])
            ->assertRedirect(route('onboarding.channel'));

        $this->actingAs($user)
            ->post(route('onboarding.channel.store'), [
                'name' => 'Grace Church',
                'slug' => 'grace-church',
                'theme_color' => '#3d9b7a',
            ])
            ->assertRedirect(route('creator.home'));

        $this->assertDatabaseHas('organizations', [
            'slug' => 'grace-church',
            'creator_type' => CreatorType::Church->value,
        ]);

        $org = Organization::query()->where('slug', 'grace-church')->first();
        $this->assertNotNull($org);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrgRole::Owner->value,
        ]);
        $this->assertDatabaseHas('streams', [
            'organization_id' => $org->id,
            'title' => 'Grace Church',
        ]);
        $freePlan = Plan::query()->where('slug', 'free')->first();
        $this->assertNotNull($freePlan);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $org->id,
            'plan_id' => $freePlan->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_free_plan_activates_without_paystack(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);

        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Free Org', 'slug' => 'free-org']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);

        $plan = Plan::query()->create([
            'name' => 'Free',
            'slug' => 'free',
            'amount' => 0,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['max_streams' => 1],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('billing.checkout', $plan))
            ->assertRedirect(route('creator.home'));

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
        $this->assertTrue($org->fresh()->allowsBroadcast());
    }

    public function test_middleware_blocks_stream_create_when_subscription_cancelled(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create([
            'name' => 'Cancelled Org',
            'slug' => 'cancelled-org',
        ]);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::Cancelled,
            'ends_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('admin.streams.store'), [
                'organization_id' => $org->id,
                'title' => 'New Stream',
            ])
            ->assertRedirect(route('billing.plans'));

        $this->assertDatabaseMissing('streams', [
            'organization_id' => $org->id,
            'title' => 'New Stream',
        ]);
    }

    public function test_dashboard_redirects_creators_to_home(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Home Org', 'slug' => 'home-org']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);
        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('creator.home'));

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Home Org');
    }
}
