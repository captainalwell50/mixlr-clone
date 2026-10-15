<?php

namespace Tests\Feature;

use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Enums\DigitalLibrarySeriesStatus;
use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\DigitalLibraryCategory;
use App\Models\DigitalLibraryPurchase;
use App\Models\DigitalLibrarySeller;
use App\Models\DigitalLibrarySeries;
use App\Models\DigitalLibraryTeacher;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlatformFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_finance_overview(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertSee('Platform Finance Overview')
            ->assertSee('Commercial Control Center')
            ->assertSee('Monthly Recurring (MRR)');
    }

    public function test_creator_and_non_admin_cannot_access_finance_pages(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'City Church', 'slug' => 'city-'.uniqid()]);
        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);

        $routes = [
            route('admin.finance.index'),
            route('admin.finance.payments'),
            route('admin.finance.subscriptions'),
            route('admin.finance.invoices'),
            route('admin.finance.refunds'),
            route('admin.finance.affiliate-liabilities'),
            route('admin.finance.reports'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($owner)
                ->get($url)
                ->assertForbidden();
        }

        // Guest is redirected
        auth()->logout();
        $this->get(route('admin.finance.index'))
            ->assertRedirect(route('login'));
    }

    public function test_revenue_uses_confirmed_payments_only(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'Faith Chapel', 'slug' => 'faith-'.uniqid()]);
        $plan = Plan::query()->create([
            'name' => 'Pro Plan',
            'slug' => 'pro-'.uniqid(),
            'amount' => 1500000, // 15,000 NGN
            'currency' => 'NGN',
            'interval' => 'monthly',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // Succeeded checkout
        PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'reference' => 'PAY-CONFIRMED-001',
            'status' => PaymentCheckout::STATUS_SUCCEEDED,
            'amount' => 1500000,
            'currency' => 'NGN',
            'verified_at' => now(),
        ]);

        // Failed checkout (must be excluded from revenue)
        PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'reference' => 'PAY-FAILED-002',
            'status' => PaymentCheckout::STATUS_FAILED,
            'amount' => 2000000,
            'currency' => 'NGN',
            'created_at' => now(),
        ]);

        // Pending checkout (must be excluded from revenue)
        PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'reference' => 'PAY-PENDING-003',
            'status' => PaymentCheckout::STATUS_PENDING,
            'amount' => 3000000,
            'currency' => 'NGN',
            'created_at' => now(),
        ]);

        $service = app(PlatformFinanceService::class);
        $metrics = $service->getOverviewMetrics();

        // 15,000.00 NGN = 1500000 minor
        $this->assertSame(1500000, $metrics['revenue_this_month_minor']);
        $this->assertSame(1500000, $metrics['revenue_total_minor']);

        $response = $this->actingAs($admin)->get(route('admin.finance.index'));
        $response->assertOk()
            ->assertSee('15,000.00')
            ->assertDontSee('35,000.00'); // Sum of failed or pending would be 65,000
    }

    public function test_refund_reduces_net_revenue_and_is_reported_accurately(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'Grace Church', 'slug' => 'grace-'.uniqid()]);
        $seller = DigitalLibrarySeller::query()->create([
            'organization_id' => $org->id,
            'status' => 'approved',
        ]);
        $teacher = DigitalLibraryTeacher::query()->create([
            'seller_id' => $seller->id,
            'display_name' => 'Pastor John',
            'slug' => 'john-'.uniqid(),
            'status' => 'active',
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
            'title' => 'Leadership Masterclass',
            'slug' => 'leadership-'.uniqid(),
            'description' => 'Test description',
            'status' => 'published',
            'currency' => 'NGN',
            'price_minor' => 500000,
        ]);

        DigitalLibraryPurchase::query()->create([
            'user_id' => $admin->id,
            'series_id' => $series->id,
            'seller_id' => $seller->id,
            'provider' => 'paystack',
            'provider_reference' => 'REF-REFUND-001',
            'listed_price_minor' => 500000,
            'amount_collected_minor' => 500000,
            'currency' => 'NGN',
            'platform_fee_bps' => 1000,
            'status' => DigitalLibraryPurchase::STATUS_REFUNDED,
            'paid_at' => now()->subDays(5),
            'refunded_amount_minor' => 500000,
            'refunded_at' => now(),
        ]);

        $service = app(PlatformFinanceService::class);
        $metrics = $service->getOverviewMetrics();

        $this->assertSame(500000, $metrics['refunds_this_month_minor']);

        $this->actingAs($admin)
            ->get(route('admin.finance.refunds'))
            ->assertOk()
            ->assertSee('Leadership Masterclass')
            ->assertSee('5,000.00');
    }

    public function test_mrr_calculation_uses_active_paid_subscriptions_only(): void
    {
        $org1 = Organization::query()->create(['name' => 'Org 1', 'slug' => 'org-1-'.uniqid()]);
        $org2 = Organization::query()->create(['name' => 'Org 2', 'slug' => 'org-2-'.uniqid()]);
        $org3 = Organization::query()->create(['name' => 'Org 3', 'slug' => 'org-3-'.uniqid()]);
        $org4 = Organization::query()->create(['name' => 'Org 4', 'slug' => 'org-4-'.uniqid()]);

        $paidPlanMonthly = Plan::query()->create([
            'name' => 'Pro Monthly',
            'slug' => 'pro-monthly-'.uniqid(),
            'amount' => 1000000, // 10,000 NGN
            'currency' => 'NGN',
            'interval' => 'monthly',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $paidPlanYearly = Plan::query()->create([
            'name' => 'Pro Annual',
            'slug' => 'pro-annual-'.uniqid(),
            'amount' => 12000000, // 120,000 NGN / 12 = 10,000/mo
            'currency' => 'NGN',
            'interval' => 'annually',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $freePlan = Plan::query()->create([
            'name' => 'Free Starter',
            'slug' => 'free',
            'amount' => 0,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        // 1. Active paid monthly: 10,000 NGN MRR
        Subscription::query()->create([
            'organization_id' => $org1->id,
            'plan_id' => $paidPlanMonthly->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // 2. Active paid yearly: 10,000 NGN MRR normalized
        Subscription::query()->create([
            'organization_id' => $org2->id,
            'plan_id' => $paidPlanYearly->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // 3. Active free: 0 MRR
        Subscription::query()->create([
            'organization_id' => $org3->id,
            'plan_id' => $freePlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // 4. Cancelled paid plan: 0 MRR (must be excluded)
        Subscription::query()->create([
            'organization_id' => $org4->id,
            'plan_id' => $paidPlanMonthly->id,
            'status' => SubscriptionStatus::Cancelled,
        ]);

        $service = app(PlatformFinanceService::class);
        $metrics = $service->getOverviewMetrics();

        // 10,000 (monthly) + 10,000 (annual normalized) = 20,000 NGN = 2,000,000 minor
        $this->assertSame(2, $metrics['active_paid_subscriptions_count']);
        $this->assertSame(2000000, $metrics['current_mrr_minor']);
    }

    public function test_plan_distribution_calculates_percentages_and_mrr_contributions(): void
    {
        $org1 = Organization::query()->create(['name' => 'Church A', 'slug' => 'a-'.uniqid()]);
        $org2 = Organization::query()->create(['name' => 'Church B', 'slug' => 'b-'.uniqid()]);

        $starterPlan = Plan::query()->create([
            'name' => 'Starter Plan',
            'slug' => 'starter-'.uniqid(),
            'amount' => 500000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'organization_id' => $org1->id,
            'plan_id' => $starterPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Subscription::query()->create([
            'organization_id' => $org2->id,
            'plan_id' => $starterPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $service = app(PlatformFinanceService::class);
        $distribution = $service->getPlanDistribution();

        $starterRow = $distribution->firstWhere('plan.id', $starterPlan->id);
        $this->assertNotNull($starterRow);
        $this->assertSame(2, $starterRow['active_count']);
        $this->assertEquals(100.0, $starterRow['percentage']);
        $this->assertSame(1000000, $starterRow['mrr_minor']);
    }

    public function test_affiliate_liabilities_are_treated_as_liabilities_not_subtracted_from_gross(): void
    {
        $admin = User::factory()->admin()->create();
        $affUser = User::factory()->create();
        $account = AffiliateAccount::query()->create([
            'user_id' => $affUser->id,
            'referral_code' => 'AFF-001',
            'status' => 'approved',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Lekki',
        ]);
        $org = Organization::query()->create(['name' => 'Aff Org', 'slug' => 'aff-org-'.uniqid()]);

        // Succeeded checkout: 10,000 NGN
        PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'reference' => 'PAY-AFF-01',
            'status' => PaymentCheckout::STATUS_SUCCEEDED,
            'amount' => 1000000,
            'currency' => 'NGN',
            'verified_at' => now(),
        ]);

        // Held commission: 2,000 NGN
        AffiliateCommission::query()->create([
            'affiliate_account_id' => $account->id,
            'organization_id' => $org->id,
            'payment_reference' => 'PAY-AFF-01',
            'idempotency_key' => 'idem-aff-01',
            'attribution_source' => 'referral_code',
            'gross_amount_minor' => 1000000,
            'commission_rate_bps' => 2000,
            'commission_amount_minor' => 200000,
            'currency' => 'NGN',
            'status' => AffiliateCommissionStatus::Held,
            'hold_until' => now()->addDays(14),
            'confirmed_at' => now(),
        ]);

        // Payable commission: 1,500 NGN
        AffiliateCommission::query()->create([
            'affiliate_account_id' => $account->id,
            'organization_id' => $org->id,
            'payment_reference' => 'PAY-AFF-02',
            'idempotency_key' => 'idem-aff-02',
            'attribution_source' => 'referral_code',
            'gross_amount_minor' => 1000000,
            'commission_rate_bps' => 1500,
            'commission_amount_minor' => 150000,
            'currency' => 'NGN',
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDays(5),
            'confirmed_at' => now()->subDays(35),
            'payable_at' => now()->subDay(),
        ]);

        $service = app(PlatformFinanceService::class);
        $metrics = $service->getOverviewMetrics();

        // Gross subscription revenue must remain 10,000 NGN (not reduced by commissions)
        $this->assertSame(1000000, $metrics['revenue_this_month_minor']);
        $this->assertSame(200000, $metrics['affiliate_held_minor']);
        $this->assertSame(150000, $metrics['affiliate_payable_minor']);

        $this->actingAs($admin)
            ->get(route('admin.finance.affiliate-liabilities'))
            ->assertOk()
            ->assertSee('1,500.00')
            ->assertSee('2,000.00');
    }

    public function test_kpi_drill_down_routes_all_respond_ok(): void
    {
        $admin = User::factory()->admin()->create();

        $routes = [
            'admin.finance.index',
            'admin.finance.payments',
            'admin.finance.subscriptions',
            'admin.finance.invoices',
            'admin.finance.refunds',
            'admin.finance.affiliate-liabilities',
            'admin.finance.reports',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_dashboard_renders_with_zero_financial_activity(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertSee('₦0.00')
            ->assertSee('+0.0% MoM');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform Business Overview')
            ->assertSee('MRR (NGN)')
            ->assertSee('Subscription Performance');
    }

    public function test_super_admin_home_dashboard_renders_mandated_sections(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Platform Business Overview')
            ->assertSee('MRR (NGN)')
            ->assertSee('Revenue (Mo)')
            ->assertSee('Subscription Performance')
            ->assertSee('Needs Attention')
            ->assertSee('Live Platform Activity')
            ->assertSee('Marketplace &middot; Digital Library', false)
            ->assertSee('Affiliate Programme &amp; Liabilities', false)
            ->assertSee('Platform Health &amp; Ingest Delivery', false);
    }

    public function test_signups_today_and_yesterday_comparison(): void
    {
        // 3 signups today
        User::factory()->count(3)->create(['created_at' => now()]);
        // 2 signups yesterday
        User::factory()->count(2)->create(['created_at' => now()->subDay()->startOfDay()->addHours(2)]);

        $service = app(PlatformFinanceService::class);
        $growth = $service->getGrowthMetrics();

        $this->assertSame(3, $growth['signups_today']);
        $this->assertSame(2, $growth['signups_yesterday']);
        $this->assertSame(1, $growth['signups_today_change']);
        $this->assertSame(50.0, $growth['signups_today_percent']);
    }

    public function test_signups_this_month_and_mom_signup_growth(): void
    {
        // 5 signups this month
        User::factory()->count(5)->create(['created_at' => now()->startOfMonth()->addDays(2)]);
        // 4 signups last month
        User::factory()->count(4)->create(['created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2)]);

        $service = app(PlatformFinanceService::class);
        $growth = $service->getGrowthMetrics();

        $this->assertSame(5, $growth['signups_this_month']);
        $this->assertSame(4, $growth['signups_last_month']);
        $this->assertSame(1, $growth['signups_mom_change']);
        $this->assertSame(25.0, $growth['signups_mom_percent']);
    }

    public function test_zero_denominator_growth_percentage_handling(): void
    {
        // No users last month, 2 this month
        User::factory()->count(2)->create(['created_at' => now()]);

        $service = app(PlatformFinanceService::class);
        $growth = $service->getGrowthMetrics();

        // When denominator is zero and current > 0, growth percent is 100.0%
        $this->assertSame(100.0, $growth['signups_mom_percent']);

        // Empty database for new check
        User::query()->delete();
        $growthClean = $service->getGrowthMetrics();

        // When both denominator and current are zero, growth percent is 0.0%
        $this->assertSame(0.0, $growthClean['signups_mom_percent']);
        $this->assertSame(0.0, $growthClean['signups_today_percent']);
    }

    public function test_organization_growth_metric_this_month(): void
    {
        // Clean slate for organization growth test
        Organization::query()->delete();

        // 3 orgs this month
        Organization::forceCreate(['name' => 'Org A', 'slug' => 'org-a-'.uniqid(), 'created_at' => now()]);
        Organization::forceCreate(['name' => 'Org B', 'slug' => 'org-b-'.uniqid(), 'created_at' => now()]);
        Organization::forceCreate(['name' => 'Org C', 'slug' => 'org-c-'.uniqid(), 'created_at' => now()]);

        // 1 org last month
        Organization::forceCreate([
            'name' => 'Org Past',
            'slug' => 'org-past-'.uniqid(),
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDay(),
        ]);

        $service = app(PlatformFinanceService::class);
        $growth = $service->getGrowthMetrics();

        $this->assertSame(3, $growth['new_organizations_this_month']);
        $this->assertSame(1, $growth['new_organizations_last_month']);
        $this->assertSame(2, $growth['new_organizations_mom_change']);
        $this->assertSame(200.0, $growth['new_organizations_mom_percent']);
    }

    public function test_first_paid_creator_conversion_metric(): void
    {
        $org1 = Organization::query()->create(['name' => 'First Church', 'slug' => 'first-'.uniqid()]);
        $org2 = Organization::query()->create(['name' => 'Second Church', 'slug' => 'second-'.uniqid()]);

        // First paid payment for Org 1 this month
        PaymentCheckout::query()->create([
            'organization_id' => $org1->id,
            'reference' => 'PAY-ORG1-01',
            'status' => PaymentCheckout::STATUS_SUCCEEDED,
            'amount' => 1500000,
            'currency' => 'NGN',
            'verified_at' => now(),
        ]);

        // Second payment for Org 1 this month (should not double-count Org 1 as new paid creator)
        PaymentCheckout::query()->create([
            'organization_id' => $org1->id,
            'reference' => 'PAY-ORG1-02',
            'status' => PaymentCheckout::STATUS_SUCCEEDED,
            'amount' => 1500000,
            'currency' => 'NGN',
            'verified_at' => now(),
        ]);

        // First paid payment for Org 2 last month
        PaymentCheckout::query()->create([
            'organization_id' => $org2->id,
            'reference' => 'PAY-ORG2-01',
            'status' => PaymentCheckout::STATUS_SUCCEEDED,
            'amount' => 1500000,
            'currency' => 'NGN',
            'verified_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
        ]);

        $service = app(PlatformFinanceService::class);
        $growth = $service->getGrowthMetrics();

        $this->assertSame(1, $growth['new_paid_creators_this_month']);
        $this->assertSame(1, $growth['new_paid_creators_last_month']);
        $this->assertSame(0, $growth['new_paid_creators_mom_change']);
        $this->assertSame(0.0, $growth['new_paid_creators_mom_percent']);
    }

    public function test_failed_payment_excluded_from_paid_creator_conversion(): void
    {
        $org = Organization::query()->create(['name' => 'Failed Org', 'slug' => 'fail-'.uniqid()]);

        PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'reference' => 'PAY-FAIL-01',
            'status' => PaymentCheckout::STATUS_FAILED,
            'amount' => 1500000,
            'currency' => 'NGN',
            'created_at' => now(),
        ]);

        $service = app(PlatformFinanceService::class);
        $growth = $service->getGrowthMetrics();

        $this->assertSame(0, $growth['new_paid_creators_this_month']);
    }

    public function test_growth_curve_bucketing_across_7d_30d_90d_12m(): void
    {
        $service = app(PlatformFinanceService::class);

        $curve7d = $service->getGrowthCurve('7d');
        $this->assertCount(7, $curve7d['labels']);
        $this->assertCount(7, $curve7d['series']['user_signups']);
        $this->assertCount(7, $curve7d['series']['organizations_created']);
        $this->assertCount(7, $curve7d['series']['first_paid_conversions']);

        $curve30d = $service->getGrowthCurve('30d');
        $this->assertCount(30, $curve30d['labels']);
        $this->assertCount(30, $curve30d['series']['user_signups']);
        $this->assertCount(30, $curve30d['series']['organizations_created']);
        $this->assertCount(30, $curve30d['series']['first_paid_conversions']);

        $curve90d = $service->getGrowthCurve('90d');
        $this->assertCount(90, $curve90d['labels']);
        $this->assertCount(90, $curve90d['series']['user_signups']);

        $curve12m = $service->getGrowthCurve('12m');
        $this->assertCount(12, $curve12m['labels']);
        $this->assertCount(12, $curve12m['series']['user_signups']);
        $this->assertCount(12, $curve12m['series']['organizations_created']);
        $this->assertCount(12, $curve12m['series']['first_paid_conversions']);
    }

    public function test_affiliate_hold_duration_is_14_days_governed_by_hold_until(): void
    {
        $affUser = User::factory()->create();
        $account = AffiliateAccount::query()->create([
            'user_id' => $affUser->id,
            'referral_code' => 'AFF-HOLD-14',
            'status' => 'approved',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Lekki',
        ]);
        $org = Organization::query()->create(['name' => 'Hold Org', 'slug' => 'hold-'.uniqid()]);

        // Held commission maturing in 14 days
        $commission = AffiliateCommission::query()->create([
            'affiliate_account_id' => $account->id,
            'organization_id' => $org->id,
            'payment_reference' => 'PAY-AFF-14',
            'idempotency_key' => 'idem-aff-14',
            'attribution_source' => 'referral_code',
            'gross_amount_minor' => 1000000,
            'commission_rate_bps' => 2000,
            'commission_amount_minor' => 200000,
            'currency' => 'NGN',
            'status' => AffiliateCommissionStatus::Held,
            'hold_until' => now()->addDays(14),
            'confirmed_at' => now(),
        ]);

        $service = app(PlatformFinanceService::class);
        $overview = $service->getOverviewMetrics();

        $this->assertSame(200000, $overview['affiliate_held_minor']);
        $this->assertTrue($commission->hold_until->isFuture());
    }

    public function test_no_30_day_hard_code_in_finance_service(): void
    {
        $reflection = new \ReflectionClass(PlatformFinanceService::class);
        $content = file_get_contents($reflection->getFileName());

        $this->assertStringNotContainsString('addDays(30)', $content);
        $this->assertStringNotContainsString('subDays(30)', $content);
        $this->assertStringNotContainsString('30 days', strtolower($content));
    }

    public function test_digital_library_refund_subtracted_exactly_once_in_net_revenue(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::query()->create(['name' => 'DL Org', 'slug' => 'dl-'.uniqid()]);
        $seller = DigitalLibrarySeller::query()->create(['organization_id' => $org->id, 'status' => 'approved']);
        $teacher = DigitalLibraryTeacher::query()->create(['seller_id' => $seller->id, 'display_name' => 'Pastor Dave', 'slug' => 'dave-'.uniqid(), 'status' => 'active']);
        $cat = DigitalLibraryCategory::query()->create(['name' => 'Audio', 'slug' => 'audio-'.uniqid()]);
        $series = DigitalLibrarySeries::query()->create([
            'organization_id' => $org->id,
            'seller_id' => $seller->id,
            'teacher_id' => $teacher->id,
            'category_id' => $cat->id,
            'title' => 'Wisdom Series',
            'slug' => 'wisdom-'.uniqid(),
            'description' => 'A wisdom series.',
            'status' => DigitalLibrarySeriesStatus::Published,
            'currency' => 'NGN',
            'price_minor' => 500000,
        ]);

        DigitalLibraryPurchase::query()->create([
            'user_id' => $admin->id,
            'series_id' => $series->id,
            'seller_id' => $seller->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref-dl-ref-1',
            'listed_price_minor' => 500000,
            'amount_collected_minor' => 500000,
            'currency' => 'NGN',
            'status' => DigitalLibraryPurchase::STATUS_PARTIALLY_REFUNDED,
            'paid_at' => now(),
            'refunded_amount_minor' => 100000,
            'refunded_at' => now(),
        ]);

        $service = app(PlatformFinanceService::class);
        $metrics = $service->getOverviewMetrics();

        // Digital Library gross = 5,000 NGN, refund = 1,000 NGN, net = 4,000 NGN
        $this->assertSame(500000, $metrics['dl_gross_this_month_minor']);
        $this->assertSame(100000, $metrics['dl_refunds_this_month_minor']);
        $this->assertSame(400000, $metrics['dl_net_this_month_minor']);
        $this->assertSame(400000, $metrics['platform_net_collected_this_month_minor']);
        $this->assertSame(100000, $metrics['refunds_this_month_minor']);
    }

    public function test_subscription_refund_subtracted_exactly_once_in_net_revenue(): void
    {
        $org = Organization::query()->create(['name' => 'Sub Org', 'slug' => 'sub-'.uniqid()]);

        // Subscription payment: 10,000 NGN
        PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'reference' => 'PAY-SUB-10K',
            'status' => PaymentCheckout::STATUS_SUCCEEDED,
            'amount' => 1000000,
            'currency' => 'NGN',
            'verified_at' => now(),
        ]);

        // Subscription refund: 2,000 NGN
        PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'reference' => 'PAY-SUB-REF-2K',
            'status' => 'refunded',
            'amount' => 200000,
            'currency' => 'NGN',
            'verified_at' => now(),
        ]);

        $service = app(PlatformFinanceService::class);
        $metrics = $service->getOverviewMetrics();

        // Creator subscription gross = 10,000 NGN, refund = 2,000 NGN, net = 8,000 NGN
        $this->assertSame(1000000, $metrics['subscription_gross_this_month_minor']);
        $this->assertSame(200000, $metrics['subscription_refunds_this_month_minor']);
        $this->assertSame(800000, $metrics['subscription_net_this_month_minor']);
        $this->assertSame(800000, $metrics['platform_net_collected_this_month_minor']);
        $this->assertSame(200000, $metrics['refunds_this_month_minor']);
    }

    public function test_custom_plan_mrr_policy_active_paid_included_unpriced_excluded(): void
    {
        $org1 = Organization::query()->create(['name' => 'Custom Paid Org', 'slug' => 'cust-paid-'.uniqid()]);
        $org2 = Organization::query()->create(['name' => 'Custom Unpriced Org', 'slug' => 'cust-unpriced-'.uniqid()]);
        $org3 = Organization::query()->create(['name' => 'Standard Paid Org', 'slug' => 'std-paid-'.uniqid()]);

        // 1. Custom plan with defined pricing: 25,000 NGN / month
        $customPaidPlan = Plan::query()->create([
            'name' => 'Enterprise Custom',
            'slug' => 'enterprise-custom-'.uniqid(),
            'amount' => 2500000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'is_custom' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        // 2. Unpriced custom plan: 0 NGN / Contact sales
        $customUnpricedPlan = Plan::query()->create([
            'name' => 'Contact Sales Custom',
            'slug' => 'custom-contact-sales-'.uniqid(),
            'amount' => 0,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'is_custom' => true,
            'is_active' => true,
            'sort_order' => 11,
        ]);

        // 3. Standard paid plan: 15,000 NGN / month
        $standardPaidPlan = Plan::query()->create([
            'name' => 'Pro Standard',
            'slug' => 'pro-std-'.uniqid(),
            'amount' => 1500000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'is_custom' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Attach active subscriptions
        Subscription::query()->create([
            'organization_id' => $org1->id,
            'plan_id' => $customPaidPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Subscription::query()->create([
            'organization_id' => $org2->id,
            'plan_id' => $customUnpricedPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Subscription::query()->create([
            'organization_id' => $org3->id,
            'plan_id' => $standardPaidPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $service = app(PlatformFinanceService::class);
        $overview = $service->getOverviewMetrics();
        $distribution = $service->getPlanDistribution();

        // Authoritative policy:
        // - Custom plan with amount > 0 is included (25,000 NGN)
        // - Standard plan with amount > 0 is included (15,000 NGN)
        // - Unpriced custom plan (amount = 0) contributes ₦0 to MRR
        // Total MRR: 40,000 NGN (4,000,000 minor)
        // Active paid subscriptions count: 2 (Org 1 + Org 3; Org 2 is excluded from paid MRR count)
        $this->assertSame(4000000, $overview['current_mrr_minor']);
        $this->assertSame(2, $overview['active_paid_subscriptions_count']);

        // Check distribution item for unpriced custom plan
        $unpricedItem = $distribution->firstWhere('plan.id', $customUnpricedPlan->id);
        $this->assertNotNull($unpricedItem);
        $this->assertSame(0, $unpricedItem['mrr_minor']);
        $this->assertSame(0.0, $unpricedItem['percentage']);

        // Check distribution item for priced custom plan
        $pricedItem = $distribution->firstWhere('plan.id', $customPaidPlan->id);
        $this->assertNotNull($pricedItem);
        $this->assertSame(2500000, $pricedItem['mrr_minor']);
        $this->assertSame(50.0, $pricedItem['percentage']);
    }
}
