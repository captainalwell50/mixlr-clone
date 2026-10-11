<?php

namespace Tests\Feature;

use App\Enums\DigitalLibrarySellerStatus;
use App\Enums\DigitalLibrarySeriesStatus;
use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Enums\SubscriptionStatus;
use App\Models\DigitalLibraryPurchase;
use App\Models\DigitalLibrarySeller;
use App\Models\DigitalLibrarySeries;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Recording;
use App\Models\Stream;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreatorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createStream(Organization $org, array $attributes = []): Stream
    {
        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => (string) Str::uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
            ...$attributes,
        ]);
    }

    public function test_creator_can_access_creator_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create([
            'name' => 'Grace Community Church',
            'slug' => 'grace-church',
        ]);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $this->createStream($org, ['title' => 'Main Sanctuary']);

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Grace Community Church')
            ->assertSee('Creator Operations')
            ->assertSee('Quick Actions')
            ->assertSee('Studio Offline');
    }

    public function test_admin_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('creator.home'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('creator.home'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_organization_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_multi_org_user_can_switch_organization_context(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org1 = Organization::query()->create(['name' => 'First Baptist', 'slug' => 'first-baptist']);
        $org2 = Organization::query()->create(['name' => 'Second Baptist', 'slug' => 'second-baptist']);

        $org1->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $org2->users()->attach($user->id, ['role' => OrgRole::Admin->value]);

        $this->createStream($org1, ['title' => 'First Stream']);
        $this->createStream($org2, ['title' => 'Second Stream']);

        // Default loads first org
        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('First Baptist');

        // Explicit switch via query param
        $this->actingAs($user)
            ->get(route('creator.home', ['org' => 'second-baptist']))
            ->assertOk()
            ->assertSee('Second Baptist');

        // Session remembers switched org
        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Second Baptist');
    }

    public function test_live_hero_state_displayed_when_stream_is_live(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Live Chapel', 'slug' => 'live-chapel']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $stream = $this->createStream($org, [
            'title' => 'Sunday Live',
            'status' => StreamStatus::Live,
            'started_at' => now()->subMinutes(25),
        ]);

        Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Sunday Morning Glory',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now()->subMinutes(25),
        ]);

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('On Air Now')
            ->assertSee('Sunday Morning Glory')
            ->assertSee('Return to Studio');
    }

    public function test_offline_hero_state_shows_next_scheduled_event(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Calvary Chapel', 'slug' => 'calvary-chapel']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $stream = $this->createStream($org);

        Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Midweek Bible Study',
            'status' => EventStatus::Scheduled,
            'access' => EventAccess::Public,
            'scheduled_at' => now()->addDays(2),
        ]);

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Studio Offline')
            ->assertSee('Next Scheduled Service')
            ->assertSee('Midweek Bible Study');
    }

    public function test_authoritative_kpi_counts_are_accurate(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Harvest Church', 'slug' => 'harvest-church']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $stream = $this->createStream($org);

        // 2 broadcasts this month
        Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Broadcast 1',
            'status' => EventStatus::Ended,
            'started_at' => now()->startOfMonth()->addDays(1),
            'ended_at' => now()->startOfMonth()->addDays(1)->addHours(1),
        ]);
        Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Broadcast 2',
            'status' => EventStatus::Ended,
            'started_at' => now()->startOfMonth()->addDays(2),
            'ended_at' => now()->startOfMonth()->addDays(2)->addHours(1),
        ]);

        // 1 upcoming event
        Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Upcoming Event',
            'status' => EventStatus::Scheduled,
            'scheduled_at' => now()->addDays(3),
        ]);

        // 1 published podcast
        Recording::query()->create([
            'stream_id' => $stream->id,
            'title' => 'Faith & Grace Episode 1',
            'is_public' => true,
            'relative_path' => 'recordings/test.mp3',
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Broadcasts This Month')
            ->assertSee('Upcoming Events')
            ->assertSee('Podcasts Published');
    }

    public function test_role_based_visibility(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $member = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Metro Chapel', 'slug' => 'metro-chapel']);

        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);
        $org->users()->attach($member->id, ['role' => OrgRole::Member->value]);

        $this->createStream($org);

        // Owner sees billing controls
        $this->actingAs($owner)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Manage billing');

        // Member does not see billing controls
        $this->actingAs($member)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertDontSee('Manage billing');
    }

    public function test_approved_digital_library_seller_metrics(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'The Word Church', 'slug' => 'the-word']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $this->createStream($org);

        $seller = DigitalLibrarySeller::query()->create([
            'organization_id' => $org->id,
            'status' => DigitalLibrarySellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $teacher = \App\Models\DigitalLibraryTeacher::query()->create([
            'organization_id' => $org->id,
            'seller_id' => $seller->id,
            'display_name' => 'Pastor John',
            'slug' => 'pastor-john',
            'status' => \App\Enums\DigitalLibraryTeacherStatus::Active,
        ]);

        $category = \App\Models\DigitalLibraryCategory::query()->create([
            'name' => 'Theology',
            'slug' => 'theology',
        ]);

        $series = DigitalLibrarySeries::query()->create([
            'organization_id' => $org->id,
            'seller_id' => $seller->id,
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
            'title' => 'Walking in Wisdom',
            'slug' => 'walking-in-wisdom',
            'description' => 'A wonderful teaching series on wisdom.',
            'status' => DigitalLibrarySeriesStatus::Published,
            'price_minor' => 500000,
            'currency' => 'NGN',
        ]);

        DigitalLibraryPurchase::query()->create([
            'user_id' => $user->id,
            'seller_id' => $seller->id,
            'series_id' => $series->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref_'.Str::random(12),
            'listed_price_minor' => 500000,
            'amount_collected_minor' => 500000,
            'currency' => 'NGN',
            'status' => DigitalLibraryPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Approved Seller')
            ->assertSee('5,000.00'); // 500,000 minor = 5,000.00 NGN
    }

    public function test_attention_queue_shows_past_due_alert(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'City Church', 'slug' => 'city-church']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $this->createStream($org);

        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::PastDue,
        ]);

        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Subscription Past Due')
            ->assertSee('Manage Billing');
    }

    public function test_creator_shell_performance_and_query_budget(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'Metro Chapel', 'slug' => 'metro-chapel']);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $stream = $this->createStream($org, ['title' => 'Main Stream']);

        $plan = Plan::query()->create([
            'name' => 'Pro Plan',
            'slug' => 'pro',
            'amount' => 1500000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Subscription::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        DigitalLibrarySeller::query()->create([
            'organization_id' => $org->id,
            'status' => DigitalLibrarySellerStatus::Approved,
            'approved_at' => now(),
        ]);

        // 1. Audit /home (dashboard route)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('creator.home'))->assertOk();
        $homeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 2. Audit /admin/events (events index)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('admin.events.index'))->assertOk();
        $eventsQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 3. Audit /admin/streams (streams index)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('admin.streams.index'))->assertOk();
        $streamsQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 4. Audit /billing (billing plans)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('billing.plans'))->assertOk();
        $billingQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 5. Audit /digital-library/sell/{org} (Digital Library seller page)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('digital-library.sell.show', $org))->assertOk();
        $sellerQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Verify sub-pages maintain lightweight shell query footprints
        $this->assertLessThanOrEqual(25, $homeQueries, "Dashboard /home query count was {$homeQueries}");
        $this->assertLessThanOrEqual(15, $eventsQueries, "Events index query count was {$eventsQueries}");
        $this->assertLessThanOrEqual(15, $streamsQueries, "Streams index query count was {$streamsQueries}");
        $this->assertLessThanOrEqual(20, $billingQueries, "Billing plans query count was {$billingQueries}");
        $this->assertLessThanOrEqual(20, $sellerQueries, "Digital Library seller query count was {$sellerQueries}");
    }

    public function test_hostile_org_switching_is_prevented(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $orgA = Organization::query()->create(['name' => 'Org Alpha', 'slug' => 'org-alpha']);
        $orgA->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $this->createStream($orgA, ['title' => 'Alpha Stream']);

        $victim = User::factory()->create(['is_admin' => false]);
        $orgB = Organization::query()->create(['name' => 'Org Beta Victim', 'slug' => 'org-beta-victim']);
        $orgB->users()->attach($victim->id, ['role' => OrgRole::Owner->value]);
        $this->createStream($orgB, ['title' => 'Beta Stream']);

        $orgC = Organization::query()->create(['name' => 'Org Gamma Valid', 'slug' => 'org-gamma-valid']);
        $this->createStream($orgC, ['title' => 'Gamma Stream']);

        // Default loads authorized Org Alpha
        $this->actingAs($user)
            ->get(route('creator.home'))
            ->assertOk()
            ->assertSee('Org Alpha');
        $this->assertEquals($orgA->id, session('active_org_id'));

        // Attempt HOSTILE switch via unauthorized slug
        $responseSlug = $this->actingAs($user)
            ->get(route('creator.home', ['org' => 'org-beta-victim']));
        $responseSlug->assertOk();
        $responseSlug->assertSee('Org Alpha');
        $responseSlug->assertDontSee('Org Beta Victim');
        $this->assertNotEquals($orgB->id, session('active_org_id'));
        $this->assertEquals($orgA->id, session('active_org_id'));

        // Attempt HOSTILE switch via unauthorized numeric ID
        $responseId = $this->actingAs($user)
            ->get(route('creator.home', ['org' => (string) $orgB->id]));
        $responseId->assertOk();
        $responseId->assertSee('Org Alpha');
        $responseId->assertDontSee('Org Beta Victim');
        $this->assertNotEquals($orgB->id, session('active_org_id'));
        $this->assertEquals($orgA->id, session('active_org_id'));

        // Legitimate multi-org attach: now user manages Org Gamma
        $orgC->users()->attach($user->id, ['role' => OrgRole::Admin->value]);

        // Valid multi-org switch to Org Gamma succeeds
        $responseValid = $this->actingAs($user)
            ->get(route('creator.home', ['org' => 'org-gamma-valid']));
        $responseValid->assertOk();
        $responseValid->assertSee('Org Gamma Valid');
        $this->assertEquals($orgC->id, session('active_org_id'));
    }

    public function test_creator_role_permission_matrix(): void
    {
        $org = Organization::query()->create(['name' => 'Matrix Church', 'slug' => 'matrix-church']);
        $stream = $this->createStream($org, ['title' => 'Matrix Sanctuary']);

        $owner = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => false]);
        $member = User::factory()->create(['is_admin' => false]);
        $platformAdmin = User::factory()->admin()->create();

        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);
        $org->users()->attach($admin->id, ['role' => OrgRole::Admin->value]);
        $org->users()->attach($member->id, ['role' => OrgRole::Member->value]);

        $event = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Sunday Service',
            'status' => EventStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        // 1. OWNER permissions:
        $this->actingAs($owner)->get(route('creator.home'))->assertOk()->assertSee('Manage billing');
        $this->actingAs($owner)->get(route('billing.plans'))->assertOk();
        $this->actingAs($owner)->get(route('admin.organizations.members', $org))->assertOk();
        $this->actingAs($owner)->get(route('admin.organizations.customise', $org))->assertOk();
        $this->actingAs($owner)->get(route('admin.streams.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.streams.edit', $stream))->assertOk();
        $this->actingAs($owner)->get(route('admin.events.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.events.edit', $event))->assertOk();
        $this->actingAs($owner)->get(route('admin.streams.studio', $stream))->assertOk();

        // 2. ADMIN permissions:
        $this->actingAs($admin)->get(route('creator.home'))->assertOk()->assertDontSee('Manage billing');
        $this->actingAs($admin)->get(route('admin.organizations.members', $org))->assertOk();
        $this->actingAs($admin)->get(route('admin.organizations.customise', $org))->assertOk();
        $this->actingAs($admin)->get(route('admin.streams.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.streams.edit', $stream))->assertOk();
        $this->actingAs($admin)->get(route('admin.events.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.events.edit', $event))->assertOk();
        $this->actingAs($admin)->get(route('admin.streams.studio', $stream))->assertOk();

        // 3. MEMBER permissions:
        $this->actingAs($member)->get(route('creator.home'))->assertOk()->assertDontSee('Manage billing');
        $this->actingAs($member)->get(route('admin.organizations.members', $org))->assertStatus(403);
        $this->actingAs($member)->get(route('admin.organizations.customise', $org))->assertStatus(403);
        $this->actingAs($member)->get(route('admin.streams.edit', $stream))->assertStatus(403);
        $this->actingAs($member)->get(route('admin.events.create'))->assertStatus(403);
        $this->actingAs($member)->get(route('admin.events.edit', $event))->assertStatus(403);

        // 4. PLATFORM ADMIN isolation:
        $this->actingAs($platformAdmin)->get(route('creator.home'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_digital_library_seller_metrics_are_strictly_org_scoped_with_refund_semantics(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $orgA = Organization::query()->create(['name' => 'Org Alpha Ministries', 'slug' => 'org-alpha-min']);
        $orgB = Organization::query()->create(['name' => 'Org Beta Publishing', 'slug' => 'org-beta-pub']);

        $orgA->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $orgB->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $this->createStream($orgA);
        $this->createStream($orgB);

        $sellerA = DigitalLibrarySeller::query()->create([
            'organization_id' => $orgA->id,
            'status' => DigitalLibrarySellerStatus::Approved,
            'approved_at' => now(),
        ]);
        $sellerB = DigitalLibrarySeller::query()->create([
            'organization_id' => $orgB->id,
            'status' => DigitalLibrarySellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $teacherA = \App\Models\DigitalLibraryTeacher::query()->create([
            'organization_id' => $orgA->id,
            'seller_id' => $sellerA->id,
            'display_name' => 'Teacher Alpha',
            'slug' => 'teacher-alpha',
            'status' => \App\Enums\DigitalLibraryTeacherStatus::Active,
        ]);
        $teacherB = \App\Models\DigitalLibraryTeacher::query()->create([
            'organization_id' => $orgB->id,
            'seller_id' => $sellerB->id,
            'display_name' => 'Teacher Beta',
            'slug' => 'teacher-beta',
            'status' => \App\Enums\DigitalLibraryTeacherStatus::Active,
        ]);

        $category = \App\Models\DigitalLibraryCategory::query()->create([
            'name' => 'Doctrine',
            'slug' => 'doctrine',
        ]);

        $seriesA = DigitalLibrarySeries::query()->create([
            'organization_id' => $orgA->id,
            'seller_id' => $sellerA->id,
            'teacher_id' => $teacherA->id,
            'category_id' => $category->id,
            'title' => 'Faith Series Alpha',
            'slug' => 'faith-series-alpha',
            'description' => 'Description Alpha',
            'status' => DigitalLibrarySeriesStatus::Published,
            'price_minor' => 1000000,
            'currency' => 'NGN',
        ]);

        $seriesB = DigitalLibrarySeries::query()->create([
            'organization_id' => $orgB->id,
            'seller_id' => $sellerB->id,
            'teacher_id' => $teacherB->id,
            'category_id' => $category->id,
            'title' => 'Grace Series Beta',
            'slug' => 'grace-series-beta',
            'description' => 'Description Beta',
            'status' => DigitalLibrarySeriesStatus::Published,
            'price_minor' => 2500000,
            'currency' => 'NGN',
        ]);

        // Purchases for Org A:
        // 1 Paid purchase (10,000 NGN)
        DigitalLibraryPurchase::query()->create([
            'user_id' => $user->id,
            'seller_id' => $sellerA->id,
            'series_id' => $seriesA->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref_a1_'.Str::random(10),
            'listed_price_minor' => 1000000,
            'amount_collected_minor' => 1000000,
            'refunded_amount_minor' => 0,
            'currency' => 'NGN',
            'status' => DigitalLibraryPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);
        // 1 Partially refunded purchase (10,000 NGN gross, 3,000 NGN refunded)
        DigitalLibraryPurchase::query()->create([
            'user_id' => $user->id,
            'seller_id' => $sellerA->id,
            'series_id' => $seriesA->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref_a2_'.Str::random(10),
            'listed_price_minor' => 1000000,
            'amount_collected_minor' => 1000000,
            'refunded_amount_minor' => 300000,
            'currency' => 'NGN',
            'status' => DigitalLibraryPurchase::STATUS_PARTIALLY_REFUNDED,
            'paid_at' => now(),
        ]);

        // Purchases for Org B:
        // 1 Refunded purchase (25,000 NGN gross, 25,000 NGN refunded)
        DigitalLibraryPurchase::query()->create([
            'user_id' => $user->id,
            'seller_id' => $sellerB->id,
            'series_id' => $seriesB->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref_b1_'.Str::random(10),
            'listed_price_minor' => 2500000,
            'amount_collected_minor' => 2500000,
            'refunded_amount_minor' => 2500000,
            'currency' => 'NGN',
            'status' => DigitalLibraryPurchase::STATUS_REFUNDED,
            'paid_at' => now(),
        ]);

        // Inspect Org Alpha:
        $resA = $this->actingAs($user)->get(route('creator.home', ['org' => 'org-alpha-min']))->assertOk();
        $resA->assertSee('Org Alpha Ministries');
        $resA->assertSee('20,000.00'); // Org A gross: 20,000 NGN
        $resA->assertSee('17,000.00'); // Org A net: 17,000 NGN
        $resA->assertSee('3,000.00 refunded');
        $resA->assertDontSee('25,000.00'); // Org B's gross must NOT leak into Org A

        // Switch to Org Beta:
        $resB = $this->actingAs($user)->get(route('creator.home', ['org' => 'org-beta-pub']))->assertOk();
        $resB->assertSee('Org Beta Publishing');
        $resB->assertSee('25,000.00'); // Org B gross: 25,000 NGN
        $resB->assertSee('25,000.00 refunded');
        $resB->assertDontSee('20,000.00'); // Org A's gross must NOT leak into Org B
        $resB->assertDontSee('17,000.00'); // Org A's net must NOT leak into Org B
    }
}
