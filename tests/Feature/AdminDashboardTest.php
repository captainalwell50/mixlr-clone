<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\DigitalLibrarySellerStatus;
use App\Enums\DigitalLibrarySeriesStatus;
use App\Enums\DigitalLibraryTeacherStatus;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AffiliateAccount;
use App\Models\DigitalLibraryCategory;
use App\Models\DigitalLibraryPurchase;
use App\Models\DigitalLibrarySeller;
use App\Models\DigitalLibrarySeries;
use App\Models\DigitalLibraryTeacher;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Stream;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform Dashboard')
            ->assertSee('Operations Console');
    }

    public function test_non_platform_admin_church_owner_is_denied(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Grace Church', 'slug' => 'grace-'.uniqid()]);
        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);

        $this->actingAs($owner)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_church_admin_and_member_are_denied(): void
    {
        $churchAdmin = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Faith Center', 'slug' => 'faith-'.uniqid()]);
        $org->users()->attach($churchAdmin->id, ['role' => OrgRole::Admin->value]);

        $this->actingAs($churchAdmin)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $member = User::factory()->create(['is_admin' => false]);
        $org->users()->attach($member->id, ['role' => OrgRole::Member->value]);

        $this->actingAs($member)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_kpi_card_metrics_match_authoritative_database_counts(): void
    {
        $admin = User::factory()->admin()->create();

        $org = Organization::query()->create(['name' => 'Main Church', 'slug' => 'main-'.uniqid()]);
        $plan = Plan::query()->create([
            'name' => 'Pro Package',
            'slug' => 'pro-'.uniqid(),
            'amount' => 10000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // Live stream
        Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Midweek Service Live',
            'status' => StreamStatus::Live,
            'started_at' => now()->subMinutes(30),
        ]);

        // Offline stream
        Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Sunday Archive',
            'status' => StreamStatus::Offline,
        ]);

        // Broadcast today
        Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Morning Glory',
            'started_at' => now()->startOfDay()->addHours(2),
        ]);

        // Digital Library purchases & GMV
        $seller = DigitalLibrarySeller::query()->create([
            'organization_id' => $org->id,
            'status' => DigitalLibrarySellerStatus::Approved,
        ]);
        $teacher = DigitalLibraryTeacher::query()->create([
            'seller_id' => $seller->id,
            'display_name' => 'Pastor John',
            'slug' => 'pastor-john-'.uniqid(),
            'status' => DigitalLibraryTeacherStatus::Active,
        ]);
        $category = DigitalLibraryCategory::query()->create([
            'name' => 'General',
            'slug' => 'general-'.uniqid(),
        ]);
        $series = DigitalLibrarySeries::query()->create([
            'organization_id' => $org->id,
            'seller_id' => $seller->id,
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
            'title' => 'Faith Foundations',
            'slug' => 'faith-foundations-'.uniqid(),
            'description' => 'A foundational teaching series.',
            'status' => DigitalLibrarySeriesStatus::Published,
            'currency' => 'NGN',
            'price_minor' => 500000,
        ]);

        DigitalLibraryPurchase::query()->create([
            'user_id' => $admin->id,
            'series_id' => $series->id,
            'seller_id' => $seller->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref-1234',
            'listed_price_minor' => 500000,
            'amount_collected_minor' => 500000,
            'currency' => 'NGN',
            'platform_fee_bps' => 1000,
            'status' => DigitalLibraryPurchase::STATUS_PAID,
            'paid_at' => now()->subDay(),
        ]);

        // Affiliates
        AffiliateAccount::query()->create([
            'user_id' => $admin->id,
            'referral_code' => 'ALPHA77',
            'status' => AffiliateStatus::Approved,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Midweek Service Live')
            ->assertSee('Digital Library GMV')
            ->assertSee('5,000.00') // 500000 minor = 5,000.00
            ->assertSee('Library Sellers')
            ->assertSee('Active Affiliates');
    }

    public function test_attention_queue_surfaces_actionable_states(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'New Chapel', 'slug' => 'new-'.uniqid()]);

        // 1. Pending seller application
        DigitalLibrarySeller::query()->create([
            'organization_id' => $org->id,
            'status' => DigitalLibrarySellerStatus::Pending,
        ]);

        // 2. Pending affiliate application
        AffiliateAccount::query()->create([
            'user_id' => $admin->id,
            'referral_code' => 'BETA99',
            'status' => AffiliateStatus::Pending,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
        ]);

        // 3. Past due subscription
        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::PastDue,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Needs Attention')
            ->assertSee('1 seller application pending review')
            ->assertSee('1 affiliate application pending review')
            ->assertSee('1 subscription renewal is past due / failed')
            ->assertSee(route('admin.digital-library.sellers.index'))
            ->assertSee(route('admin.affiliates.index'));
    }

    public function test_navigation_renders_dedicated_admin_shell_without_public_nav(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            // Top bar controls
            ->assertSee('Operations Console')
            ->assertSee('Attention')
            ->assertSee('View site')
            // Sidebar Navigation Sections
            ->assertSee('OVERVIEW', false)
            ->assertSee('PLATFORM', false)
            ->assertSee('CONTENT', false)
            ->assertSee('COMMERCE', false)
            ->assertSee('SYSTEM', false)
            // Digital Library single top-level entry
            ->assertSee('Digital Library')
            ->assertSee('Affiliate Programme')
            // Public nav elements should NOT be primary nav
            ->assertDontSee('mkt-nav');
    }

    public function test_global_search_returns_entities(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'Zion City Church', 'slug' => 'zion-'.uniqid()]);
        Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Zion Sunrise Praise',
            'status' => StreamStatus::Offline,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.search', ['q' => 'Zion']))
            ->assertOk()
            ->assertSee('Search Results')
            ->assertSee('Zion Sunrise Praise')
            ->assertSee('Zion City Church');
    }

    public function test_active_organizations_counts_distinct_qualifying_organizations(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'Single Church', 'slug' => 'single-'.uniqid()]);

        // Create two active subscription rows for the same organization
        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::Active,
        ]);
        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Active organisations: 1');
    }

    public function test_digital_library_gmv_aggregates_ngn_only_without_fx_mixing(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'Media Church', 'slug' => 'media-'.uniqid()]);
        $seller = DigitalLibrarySeller::query()->create([
            'organization_id' => $org->id,
            'status' => DigitalLibrarySellerStatus::Approved,
        ]);
        $teacher = DigitalLibraryTeacher::query()->create([
            'seller_id' => $seller->id,
            'display_name' => 'Pastor Dave',
            'slug' => 'pastor-dave-'.uniqid(),
            'status' => DigitalLibraryTeacherStatus::Active,
        ]);
        $category = DigitalLibraryCategory::query()->create([
            'name' => 'Teachings',
            'slug' => 'teachings-'.uniqid(),
        ]);
        $seriesNgn = DigitalLibrarySeries::query()->create([
            'organization_id' => $org->id,
            'seller_id' => $seller->id,
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
            'title' => 'Faith Series NGN',
            'slug' => 'faith-ngn-'.uniqid(),
            'description' => 'NGN series',
            'status' => DigitalLibrarySeriesStatus::Published,
            'currency' => 'NGN',
            'price_minor' => 100000,
        ]);
        $seriesUsd = DigitalLibrarySeries::query()->create([
            'organization_id' => $org->id,
            'seller_id' => $seller->id,
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
            'title' => 'Faith Series USD',
            'slug' => 'faith-usd-'.uniqid(),
            'description' => 'USD series',
            'status' => DigitalLibrarySeriesStatus::Published,
            'currency' => 'USD',
            'price_minor' => 2000,
        ]);

        // NGN purchase: 1,000 NGN (100000 minor)
        DigitalLibraryPurchase::query()->create([
            'user_id' => $admin->id,
            'series_id' => $seriesNgn->id,
            'seller_id' => $seller->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref-ngn-1',
            'listed_price_minor' => 100000,
            'amount_collected_minor' => 100000,
            'currency' => 'NGN',
            'status' => DigitalLibraryPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        // USD purchase: 20 USD (2000 minor) — must not be added to NGN card
        DigitalLibraryPurchase::query()->create([
            'user_id' => $admin->id,
            'series_id' => $seriesUsd->id,
            'seller_id' => $seller->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref-usd-1',
            'listed_price_minor' => 2000,
            'amount_collected_minor' => 2000,
            'currency' => 'USD',
            'status' => DigitalLibraryPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Digital Library GMV (NGN)')
            ->assertSee('1,000.00')
            ->assertDontSee('1,020.00'); // No FX mixing of 1000 + 20
    }

    public function test_admin_shell_renders_flash_free_theme_script_and_theme_selector(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('soundmix_theme', false)
            ->assertSee('window.SoundMixTheme', false)
            ->assertSee('data-theme-option="light"', false)
            ->assertSee('data-theme-option="dark"', false)
            ->assertSee('data-theme-option="system"', false)
            ->assertSee('admin-shell', false);
    }
}
