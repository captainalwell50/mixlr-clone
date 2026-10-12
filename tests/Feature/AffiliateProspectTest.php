<?php

namespace Tests\Feature;

use App\Enums\AffiliateAttributionSource;
use App\Enums\AffiliateProspectStatus;
use App\Enums\AffiliateStatus;
use App\Enums\CreatorType;
use App\Enums\OrgRole;
use App\Models\AffiliateAccount;
use App\Models\AffiliateProspect;
use App\Models\AffiliateProspectAudit;
use App\Models\AffiliateReferralVisit;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateProgrammeSettings;
use App\Services\AffiliateProspectService;
use App\Support\ChurchIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateProspectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['app.registration_enabled' => true]);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        AffiliateProspect::flushEventListeners();
        $this->travelBack();
        Cache::flush();

        parent::tearDown();
    }

    public function test_approved_affiliate_can_plan_visit_email_and_phone_without_creating_an_organization(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'planner1');
        $before = Organization::query()->count();

        foreach ([
            'visit' => 'Harbour Chapel',
            'direct_email' => 'Lakeside Parish',
            'phone_call' => 'Northside Assembly',
        ] as $method => $church) {
            $this->actingAs($affiliate->user)
                ->post(route('affiliate.prospects.store'), $this->plan([
                    'church_name' => $church,
                    'outreach_method' => $method,
                    'notes' => 'Ask for the pastor.',
                ]))
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertSame($before, Organization::query()->count());
        $this->assertSame(0, PaymentCheckout::query()->count());
        $this->assertSame(3, AffiliateProspect::query()->count());
        $this->assertTrue(Schema::hasTable('affiliate_commissions'));
        $this->assertTrue(Schema::hasTable('affiliate_payouts'));
        $this->assertFalse(Schema::hasTable('organization_payments'));

        $visit = AffiliateProspect::query()->where('outreach_method', 'visit')->firstOrFail();
        $this->assertSame(AffiliateProspectStatus::Planned, $visit->status);
        $this->assertNotNull($visit->active_reservation_key);
        $this->assertTrue($visit->reserved_until->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));
        $this->assertSame(
            ChurchIdentity::reservationKey('NG', 'Lagos', 'Ikeja', 'Harbour Chapel'),
            $visit->active_reservation_key,
        );

        $this->actingAs($affiliate->user)
            ->get(route('affiliate.prospects.index'))
            ->assertOk()
            ->assertSee('Harbour Chapel')
            ->assertSee('Active')
            ->assertSee('Converted ÷ plans that were not cancelled.', false);
    }

    public function test_unapproved_and_suspended_affiliates_cannot_reserve_a_church(): void
    {
        foreach ([AffiliateStatus::Invited, AffiliateStatus::Pending, AffiliateStatus::Rejected, AffiliateStatus::Suspended] as $status) {
            $affiliate = $this->makeAffiliate($status, $status->value.'plan');

            $this->actingAs($affiliate->user)
                ->post(route('affiliate.prospects.store'), $this->plan())
                ->assertForbidden();
        }

        $this->assertSame(0, AffiliateProspect::query()->count());
    }

    public function test_active_cap_counts_only_current_reservations(): void
    {
        SiteSetting::putValue(AffiliateProgrammeSettings::KEY, json_encode([
            'prospect_active_limit' => 1,
        ]));
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'capped01');

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'First Chapel']))
            ->assertRedirect();

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Second Chapel']))
            ->assertSessionHasErrors(['church_name' => AffiliateProspectService::CAP_MESSAGE]);

        $this->travel(31)->days();

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Second Chapel']))
            ->assertRedirect();

        $this->assertSame(1, AffiliateProspect::query()->holdingReservation()->count());
    }

    public function test_another_affiliate_cannot_reserve_an_active_church_and_cannot_see_private_details(): void
    {
        $first = $this->makeAffiliate(AffiliateStatus::Approved, 'ownerone');
        $second = $this->makeAffiliate(AffiliateStatus::Approved, 'ownertwo');

        $this->actingAs($first->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'notes' => 'Private follow-up note',
                'email' => 'pastor@harbour.test',
                'phone' => '08031234567',
                'contact_name' => 'Pastor Ada',
            ]))
            ->assertRedirect();

        $prospect = AffiliateProspect::query()->firstOrFail();

        $this->actingAs($second->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'different_church' => '1',
                'address' => '12 Allen Avenue',
            ]))
            ->assertSessionHasErrors(['church_name' => AffiliateProspectService::RESERVED_MESSAGE]);

        $search = $this->actingAs($second->user)
            ->get(route('affiliate.prospects.search', [
                'church_name' => 'Harbour Chapel',
                'country_code' => 'NG',
                'state_region' => 'Lagos',
                'city' => 'Ikeja',
            ]))
            ->assertOk()
            ->assertJsonPath('state', 'reserved')
            ->assertJsonPath('message', AffiliateProspectService::RESERVED_MESSAGE);

        $body = $search->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString($first->user->email, $body);
        $this->assertStringNotContainsString($first->referral_code, $body);
        $this->assertStringNotContainsString('Private follow-up note', $body);
        $this->assertStringNotContainsString('pastor@harbour.test', $body);
        $this->assertStringNotContainsString('08031234567', $body);
        $this->assertStringNotContainsString('Pastor Ada', $body);
        $this->assertStringContainsString($prospect->reserved_until->toIso8601String(), $body);

        $this->actingAs($second->user)
            ->get(route('affiliate.prospects.show', $prospect))
            ->assertForbidden();

        $this->actingAs($second->user)
            ->get(route('affiliate.prospects.index'))
            ->assertOk()
            ->assertDontSee('Harbour Chapel');
    }

    public function test_a_reservation_that_appears_during_save_is_rejected(): void
    {
        $first = $this->makeAffiliate(AffiliateStatus::Approved, 'racewin1');
        $second = $this->makeAffiliate(AffiliateStatus::Approved, 'racelose');
        $key = ChurchIdentity::reservationKey('NG', 'Lagos', 'Ikeja', 'Race Chapel');

        AffiliateProspect::creating(function (AffiliateProspect $prospect) use ($first, $key): void {
            if ($prospect->church_name !== 'Race Chapel') {
                return;
            }

            AffiliateProspect::withoutEvents(function () use ($first, $key): void {
                if (AffiliateProspect::query()->where('active_reservation_key', $key)->exists()) {
                    return;
                }

                AffiliateProspect::query()->create([
                    'affiliate_account_id' => $first->id,
                    'outreach_method' => 'visit',
                    'church_name' => 'Race Chapel holder',
                    'normalized_church_name' => 'race chapel holder',
                    'country_code' => 'NG',
                    'state_region' => 'Lagos',
                    'city' => 'Ikeja',
                    'status' => AffiliateProspectStatus::Planned,
                    'reserved_at' => now(),
                    'reserved_until' => now()->addDays(30),
                    'active_reservation_key' => $key,
                ]);
            });
        });

        $this->actingAs($second->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Race Chapel']))
            ->assertSessionHasErrors(['church_name' => AffiliateProspectService::JUST_RESERVED_MESSAGE]);

        $this->assertSame(0, AffiliateProspect::query()->count());
    }

    public function test_same_reservation_key_cannot_be_stored_twice(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'unique01');
        $key = ChurchIdentity::reservationKey('NG', 'Lagos', 'Ikeja', 'Unique Chapel');

        AffiliateProspect::query()->create($this->row($affiliate, $key, 'Unique Chapel'));

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        AffiliateProspect::query()->create($this->row($affiliate, $key, 'Unique Chapel again'));
    }

    public function test_expired_reservation_can_be_taken_immediately_without_the_cleanup_command(): void
    {
        $first = $this->makeAffiliate(AffiliateStatus::Approved, 'expire01');
        $second = $this->makeAffiliate(AffiliateStatus::Approved, 'expire02');

        $this->travelTo('2026-06-01 12:00:00');
        $this->actingAs($first->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->travelTo('2026-07-02 12:00:00');

        $this->actingAs($second->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $original = AffiliateProspect::query()->where('affiliate_account_id', $first->id)->firstOrFail();
        $next = AffiliateProspect::query()->where('affiliate_account_id', $second->id)->firstOrFail();

        $this->assertSame(AffiliateProspectStatus::Expired, $original->status);
        $this->assertNull($original->active_reservation_key);
        $this->assertNotNull($next->active_reservation_key);
        $this->assertTrue($next->holdsReservation());
    }

    public function test_cleanup_command_relabels_stale_plans(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'cleanup1');
        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertRedirect();

        $this->travel(31)->days();
        $this->artisan('affiliates:expire-prospects')->assertSuccessful();

        $prospect = AffiliateProspect::query()->firstOrFail();
        $this->assertSame(AffiliateProspectStatus::Expired, $prospect->status);
        $this->assertNull($prospect->active_reservation_key);
    }

    public function test_registered_church_with_the_same_location_cannot_be_reserved(): void
    {
        Organization::query()->create([
            'name' => 'Harbour Chapel',
            'slug' => 'harbour-chapel',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'is_public' => true,
        ]);
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'blocked1');

        $this->actingAs($affiliate->user)
            ->get(route('affiliate.prospects.search', $this->plan()))
            ->assertJsonPath('state', 'registered')
            ->assertJsonPath('message', AffiliateProspectService::REGISTERED_MESSAGE)
            ->assertJsonPath('matches.0.name', 'Harbour Chapel')
            ->assertJsonPath('matches.0.city', 'Ikeja');

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'different_church' => '1',
                'address' => 'A different street',
            ]))
            ->assertSessionHasErrors(['church_name' => AffiliateProspectService::REGISTERED_MESSAGE]);

        $this->assertSame(0, AffiliateProspect::query()->count());
    }

    public function test_fuzzy_names_are_surfaced_and_not_merged(): void
    {
        $this->assertSame(
            ChurchIdentity::normalizeName("R.C.C.G. King's Palace"),
            ChurchIdentity::normalizeName('RCCG Kings Palace'),
        );

        $first = $this->makeAffiliate(AffiliateStatus::Approved, 'fuzzy001');
        $second = $this->makeAffiliate(AffiliateStatus::Approved, 'fuzzy002');

        $this->actingAs($first->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Community']))
            ->assertRedirect();

        $this->actingAs($second->user)
            ->get(route('affiliate.prospects.search', $this->plan(['church_name' => 'Grace Community Chapel'])))
            ->assertJsonPath('state', 'review')
            ->assertJsonPath('requires_disambiguation', true);

        $this->actingAs($second->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Community Chapel']))
            ->assertSessionHasErrors('church_name');

        $this->actingAs($second->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'church_name' => 'Grace Community Chapel',
                'different_church' => '1',
                'address' => '14 Chapel Road',
            ]))
            ->assertRedirect();

        $this->assertSame(2, AffiliateProspect::query()->holdingReservation()->count());
    }

    public function test_same_name_in_another_city_can_be_reserved(): void
    {
        $first = $this->makeAffiliate(AffiliateStatus::Approved, 'lagosaff');
        $second = $this->makeAffiliate(AffiliateStatus::Approved, 'abujaaff');

        $this->actingAs($first->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'church_name' => 'Living Faith Church',
                'city' => 'Lagos',
            ]))
            ->assertRedirect();

        $this->actingAs($second->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'church_name' => 'Living Faith Church',
                'state_region' => 'FCT',
                'city' => 'Abuja',
            ]))
            ->assertRedirect();

        $this->assertSame(2, AffiliateProspect::query()->holdingReservation()->count());
    }

    public function test_affiliate_is_sent_to_an_existing_plan_instead_of_a_duplicate(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'ownplan1');
        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertRedirect();

        $prospect = AffiliateProspect::query()->firstOrFail();

        $this->actingAs($affiliate->user)
            ->get(route('affiliate.prospects.search', $this->plan()))
            ->assertJsonPath('state', 'yours')
            ->assertJsonPath('matches.0.id', $prospect->id)
            ->assertJsonPath('matches.0.outreach_method', 'visit');

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertSessionHasErrors('church_name');

        $this->assertSame(1, AffiliateProspect::query()->count());
    }

    public function test_matching_reservation_converts_when_the_channel_is_created(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'convert1');
        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Chapel']))
            ->assertRedirect();

        $owner = User::factory()->create();
        $this->onboard($owner, 'grace-chapel', [
            'name' => 'Grace Chapel',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
        ]);

        $organization = Organization::query()->where('slug', 'grace-chapel')->firstOrFail();
        $prospect = AffiliateProspect::query()->firstOrFail();
        $attribution = $organization->affiliateAttribution()->firstOrFail();

        $this->assertSame(AffiliateProspectStatus::Converted, $prospect->status);
        $this->assertSame($organization->id, $prospect->converted_organization_id);
        $this->assertNotNull($prospect->converted_at);
        $this->assertNull($prospect->active_reservation_key);
        $this->assertSame($affiliate->id, $attribution->affiliate_account_id);
        $this->assertSame(AffiliateAttributionSource::ProspectReservation, $attribution->source);
        $this->assertTrue($attribution->isEarningEligible());
        $this->assertNull($attribution->referral_visit_id);
        $this->assertDatabaseHas('affiliate_attribution_changes', [
            'organization_id' => $organization->id,
            'new_source' => AffiliateAttributionSource::ProspectReservation->value,
            'reason' => 'Resolved from an earlier prospect reservation at channel creation.',
        ]);
        $this->assertSame(0, PaymentCheckout::query()->count());
    }

    public function test_reservation_does_not_bypass_self_referral_protection(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'selfplan');
        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Chapel']))
            ->assertRedirect();

        $this->onboard($affiliate->user, 'self-chapel', [
            'name' => 'Grace Chapel',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
        ]);

        $attribution = Organization::query()->where('slug', 'self-chapel')->firstOrFail()->affiliateAttribution()->firstOrFail();
        $prospect = AffiliateProspect::query()->firstOrFail();

        $this->assertSame(AffiliateAttributionSource::BlockedSelfReferral, $attribution->source);
        $this->assertFalse($attribution->isEarningEligible());
        $this->assertSame(AffiliateProspectStatus::Converted, $prospect->status);
        $this->assertSame($affiliate->id, $attribution->affiliate_account_id);
    }

    public function test_existing_organization_attribution_cannot_be_stolen_by_a_reservation(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'lateplan');
        $organization = Organization::query()->create([
            'name' => 'Harbour Chapel',
            'slug' => 'harbour-existing',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'is_public' => true,
        ]);

        $service = app(AffiliateAttributionService::class);
        $organic = $service->attachNewOrganization($organization, Request::create('/'));

        AffiliateProspect::query()->create($this->row(
            $affiliate,
            ChurchIdentity::reservationKey('NG', 'Lagos', 'Ikeja', 'Harbour Chapel'),
            'Harbour Chapel',
        ));

        $again = $service->attachNewOrganization($organization->fresh(), Request::create('/'));

        $this->assertTrue($again->is($organic));
        $this->assertSame(AffiliateAttributionSource::Organic, $again->source);
        $this->assertNull($again->affiliate_account_id);
        $this->assertSame(AffiliateProspectStatus::Planned, AffiliateProspect::query()->firstOrFail()->status);

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertSessionHasErrors(['church_name' => AffiliateProspectService::REGISTERED_MESSAGE]);
    }

    public function test_earlier_referral_visit_wins_when_both_claims_match_the_channel(): void
    {
        $referrer = $this->makeAffiliate(AffiliateStatus::Approved, 'earlyref');
        $planner = $this->makeAffiliate(AffiliateStatus::Approved, 'lateplan');

        $this->travelTo('2026-06-01 10:00:00');
        $this->get('/r/earlyref')->assertRedirect();
        $token = AffiliateReferralVisit::query()->firstOrFail()->token;

        $this->travelTo('2026-06-02 10:00:00');
        $this->actingAs($planner->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Chapel']))
            ->assertRedirect();

        $this->travelTo('2026-06-03 10:00:00');
        $owner = User::factory()->create();
        $this->onboard($owner, 'visit-wins', [
            'name' => 'Grace Chapel',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
        ], $token);

        $attribution = Organization::query()->where('slug', 'visit-wins')->firstOrFail()->affiliateAttribution()->firstOrFail();
        $this->assertSame(AffiliateAttributionSource::FirstTouch, $attribution->source);
        $this->assertSame($referrer->id, $attribution->affiliate_account_id);
        $this->assertSame(AffiliateProspectStatus::Planned, AffiliateProspect::query()->firstOrFail()->status);
    }

    public function test_earlier_reservation_wins_over_a_later_referral_visit(): void
    {
        $planner = $this->makeAffiliate(AffiliateStatus::Approved, 'earlypln');
        $referrer = $this->makeAffiliate(AffiliateStatus::Approved, 'lateref1');

        $this->travelTo('2026-06-01 10:00:00');
        $this->actingAs($planner->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Chapel']))
            ->assertRedirect();

        $this->travelTo('2026-06-02 10:00:00');
        $this->get('/r/lateref1')->assertRedirect();
        $visit = AffiliateReferralVisit::query()->firstOrFail();

        $this->travelTo('2026-06-03 10:00:00');
        $owner = User::factory()->create();
        $this->onboard($owner, 'plan-wins', [
            'name' => 'Grace Chapel',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
        ], $visit->token);

        $attribution = Organization::query()->where('slug', 'plan-wins')->firstOrFail()->affiliateAttribution()->firstOrFail();
        $visit->refresh();
        $this->assertSame(AffiliateAttributionSource::ProspectReservation, $attribution->source);
        $this->assertSame($planner->id, $attribution->affiliate_account_id);
        $this->assertNull($attribution->referral_visit_id);
        $this->assertNotNull($visit->converted_at);
        $this->assertSame($referrer->id, $visit->affiliate_account_id);
    }

    public function test_equal_timestamps_keep_the_referral_visit(): void
    {
        $planner = $this->makeAffiliate(AffiliateStatus::Approved, 'tieplan1');
        $referrer = $this->makeAffiliate(AffiliateStatus::Approved, 'tieref001');
        $this->travelTo('2026-06-01 10:00:00');

        $this->actingAs($planner->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Chapel']))
            ->assertRedirect();

        $visit = AffiliateReferralVisit::query()->create([
            'affiliate_account_id' => $referrer->id,
            'referral_code' => $referrer->referral_code,
            'token' => Str::random(48),
            'first_seen_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);

        $owner = User::factory()->create();
        $this->onboard($owner, 'tie-chapel', [
            'name' => 'Grace Chapel',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
        ], $visit->token);

        $attribution = Organization::query()->where('slug', 'tie-chapel')->firstOrFail()->affiliateAttribution()->firstOrFail();
        $this->assertSame(AffiliateAttributionSource::FirstTouch, $attribution->source);
        $this->assertSame($referrer->id, $attribution->affiliate_account_id);
        $this->assertSame(AffiliateProspectStatus::Planned, AffiliateProspect::query()->firstOrFail()->status);
    }

    public function test_reservation_is_not_guessed_when_the_channel_has_no_location(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'noloc001');
        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan(['church_name' => 'Grace Chapel']))
            ->assertRedirect();

        $owner = User::factory()->create();
        $this->onboard($owner, 'no-location', ['name' => 'Grace Chapel']);

        $attribution = Organization::query()->where('slug', 'no-location')->firstOrFail()->affiliateAttribution()->firstOrFail();
        $this->assertSame(AffiliateAttributionSource::Organic, $attribution->source);
        $this->assertTrue(AffiliateProspect::query()->firstOrFail()->holdsReservation());
    }

    public function test_platform_admin_can_release_a_reservation_and_a_church_manager_cannot(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'release1');
        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'notes' => 'Call the office',
            ]))
            ->assertRedirect();
        $prospect = AffiliateProspect::query()->firstOrFail();

        $manager = User::factory()->create();
        $church = Organization::query()->create([
            'name' => 'Managed Chapel',
            'slug' => 'managed-chapel',
            'is_public' => true,
        ]);
        $church->users()->attach($manager->id, ['role' => OrgRole::Owner->value]);

        $this->actingAs($manager)
            ->get(route('admin.prospects.index'))
            ->assertForbidden();
        $this->actingAs($manager)
            ->post(route('admin.prospects.release', $prospect), ['reason' => 'Not their reservation'])
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('admin.prospects.release', $prospect), ['reason' => 'ab'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->get(route('admin.prospects.show', $prospect))
            ->assertOk()
            ->assertSee($affiliate->user->email)
            ->assertSee($affiliate->referral_code)
            ->assertSee('Call the office');

        $this->actingAs($admin)
            ->post(route('admin.prospects.release', $prospect), [
                'reason' => 'Confirmed this is a duplicate of another plan.',
            ])
            ->assertRedirect(route('admin.prospects.show', $prospect));

        $prospect->refresh();
        $this->assertSame(AffiliateProspectStatus::Cancelled, $prospect->status);
        $this->assertNull($prospect->active_reservation_key);
        $audit = AffiliateProspectAudit::query()->firstOrFail();
        $this->assertSame('released', $audit->action);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('Confirmed this is a duplicate of another plan.', $audit->reason);

        $other = $this->makeAffiliate(AffiliateStatus::Approved, 'afterrel');
        $this->actingAs($other->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertRedirect();
        $this->assertSame(1, AffiliateProspect::query()->holdingReservation()->count());
    }

    public function test_affiliate_can_move_a_plan_without_setting_converted_manually(): void
    {
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'status01');
        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertRedirect();
        $prospect = AffiliateProspect::query()->firstOrFail();

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.status', $prospect), ['status' => 'contacted'])
            ->assertRedirect();
        $this->assertNotNull($prospect->fresh()->contacted_at);

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.status', $prospect), ['status' => 'follow_up'])
            ->assertRedirect();
        $this->actingAs($affiliate->user)
            ->get(route('affiliate.prospects.index', ['filter' => 'follow_up']))
            ->assertOk()
            ->assertSee('Harbour Chapel');

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.status', $prospect), ['status' => 'converted'])
            ->assertSessionHasErrors('status');
        $this->assertSame(AffiliateProspectStatus::FollowUp, $prospect->fresh()->status);

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.status', $prospect), ['status' => 'not_interested'])
            ->assertRedirect();

        $closed = $prospect->fresh();
        $this->assertSame(AffiliateProspectStatus::NotInterested, $closed->status);
        $this->assertNull($closed->active_reservation_key);
        $this->assertSame(0, AffiliateProspect::query()->holdingReservation()->count());
    }

    public function test_disabled_programme_rejects_a_new_plan(): void
    {
        SiteSetting::putValue(AffiliateProgrammeSettings::KEY, json_encode(['enabled' => false]));
        $affiliate = $this->makeAffiliate(AffiliateStatus::Approved, 'disabled');

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), $this->plan())
            ->assertSessionHasErrors('church_name');

        $this->assertSame(0, AffiliateProspect::query()->count());
    }

    public function test_shared_phone_cannot_bypass_an_active_reservation(): void
    {
        $first = $this->makeAffiliate(AffiliateStatus::Approved, 'phoneone');
        $second = $this->makeAffiliate(AffiliateStatus::Approved, 'phonetwo');

        $this->actingAs($first->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'church_name' => 'Chapel One',
                'phone' => '0803 123 4567',
            ]))
            ->assertRedirect();

        $this->actingAs($second->user)
            ->post(route('affiliate.prospects.store'), $this->plan([
                'church_name' => 'Chapel Two',
                'phone' => '(0803) 123-4567',
                'different_church' => '1',
                'address' => 'Somewhere else',
            ]))
            ->assertSessionHasErrors(['church_name' => AffiliateProspectService::RESERVED_MESSAGE]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function plan(array $overrides = []): array
    {
        return array_merge([
            'church_name' => 'Harbour Chapel',
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'outreach_method' => 'visit',
            'planned_for' => '2026-10-20',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AffiliateAccount $affiliate, ?string $key, string $name): array
    {
        return [
            'affiliate_account_id' => $affiliate->id,
            'outreach_method' => 'visit',
            'church_name' => $name,
            'normalized_church_name' => ChurchIdentity::normalizeName($name),
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'status' => AffiliateProspectStatus::Planned,
            'reserved_at' => now(),
            'reserved_until' => now()->addDays(30),
            'active_reservation_key' => $key,
        ];
    }

    private function makeAffiliate(AffiliateStatus $status, string $code): AffiliateAccount
    {
        $user = User::factory()->create();

        return AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'referral_code' => $code,
            'status' => $status,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'approved_at' => $status === AffiliateStatus::Approved ? now() : null,
            'suspended_at' => $status === AffiliateStatus::Suspended ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    private function onboard(User $user, string $slug, array $channel, ?string $token = null): void
    {
        $this->actingAs($user)
            ->post(route('onboarding.type'), ['creator_type' => CreatorType::Church->value])
            ->assertRedirect(route('onboarding.channel'));

        $request = $this->actingAs($user);
        if ($token !== null) {
            $request = $request->withCookie(AffiliateReferralVisit::COOKIE, $token);
        }

        $request->post(route('onboarding.channel.store'), array_merge([
            'slug' => $slug,
            'theme_color' => '#3d9b7a',
        ], $channel))->assertRedirect(route('creator.home'));
    }
}
