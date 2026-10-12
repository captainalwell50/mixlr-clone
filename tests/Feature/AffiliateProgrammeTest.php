<?php

namespace Tests\Feature;

use App\Enums\AffiliateAttributionSource;
use App\Enums\AffiliateStatus;
use App\Enums\CreatorType;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Models\AffiliateAccount;
use App\Models\AffiliateAttribution;
use App\Models\AffiliateAttributionChange;
use App\Models\AffiliateReferralVisit;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\SiteSetting;
use App\Models\Stream;
use App\Models\User;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AffiliateProgrammeTest extends TestCase
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
        $this->travelBack();
        Cache::flush();

        parent::tearDown();
    }

    public function test_affiliate_identity_is_separate_from_organization_membership(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $organization = $this->makeOrganization('identity-church');
        $organization->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $affiliate = $this->makeAffiliate($user, AffiliateStatus::Approved, 'identcod');

        $this->assertTrue($user->affiliateAccount->is($affiliate));
        $this->assertSame(OrgRole::Owner, $user->fresh()->orgRole($organization));
        $this->assertFalse($user->isAdmin());
        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'affiliate',
        ]);
        $this->assertNull($affiliate->payout_qualified_at);
    }

    public function test_unapproved_affiliate_code_does_not_create_attribution(): void
    {
        $approved = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'keepcode');
        $this->get('/r/keepcode')->assertRedirect(route('register'));
        $kept = AffiliateReferralVisit::query()->firstOrFail();

        foreach ([AffiliateStatus::Invited, AffiliateStatus::Pending, AffiliateStatus::Rejected, AffiliateStatus::Suspended] as $status) {
            $affiliate = $this->makeAffiliate(User::factory()->create(), $status, $status->value.'code');
            $before = Organization::query()->count();

            $this->withCookie(AffiliateReferralVisit::COOKIE, $kept->token)
                ->get('/r/'.$affiliate->referral_code)
                ->assertRedirect(route('register'));

            $this->assertSame($before, Organization::query()->count());
            $this->assertSame(1, AffiliateReferralVisit::query()->count());
            $this->assertTrue($kept->fresh()->isOpen());
            $this->assertSame($approved->id, $kept->fresh()->affiliate_account_id);
        }
    }

    public function test_approved_referral_link_records_a_visit_and_does_not_create_an_organization(): void
    {
        $affiliate = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'livecode');
        $before = Organization::query()->count();

        $response = $this->get('/r/livecode');

        $response->assertRedirect(route('register'));
        $this->assertSame($before, Organization::query()->count());

        $visit = AffiliateReferralVisit::query()->firstOrFail();
        $response->assertCookie(AffiliateReferralVisit::COOKIE, $visit->token);
        $this->assertSame($affiliate->id, $visit->affiliate_account_id);
        $this->assertSame('livecode', $visit->referral_code);
        $this->assertTrue($visit->isOpen());
        $this->assertTrue($visit->expires_at->between(now()->addDays(90)->subMinute(), now()->addDays(90)->addMinute()));
        $this->assertNotNull($visit->ip_hash);
        $this->assertSame(0, PaymentCheckout::query()->count());
    }

    public function test_first_touch_referral_is_not_replaced_by_a_later_affiliate(): void
    {
        $first = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'firstaff');
        $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'lateraff');

        $this->get('/r/firstaff')->assertRedirect(route('register'));
        $token = AffiliateReferralVisit::query()->firstOrFail()->token;

        $this->withCookie(AffiliateReferralVisit::COOKIE, $token)
            ->get('/r/lateraff')
            ->assertRedirect(route('register'));

        $this->assertSame(1, AffiliateReferralVisit::query()->count());
        $this->assertSame($first->id, AffiliateReferralVisit::query()->firstOrFail()->affiliate_account_id);

        $owner = User::factory()->create();
        $this->onboard($owner, 'first-church', $token);

        $attribution = $this->attributionFor('first-church');
        $this->assertSame(AffiliateAttributionSource::FirstTouch, $attribution->source);
        $this->assertSame($first->id, $attribution->affiliate_account_id);
        $this->assertTrue($attribution->isEarningEligible());
        $this->assertNotNull($attribution->referral_visit_id);
    }

    public function test_expired_referral_can_be_replaced(): void
    {
        $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'oldcode1');
        $replacement = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'newcode1');

        $this->get('/r/oldcode1');
        $original = AffiliateReferralVisit::query()->firstOrFail();

        $this->travel(91)->days();

        $this->withCookie(AffiliateReferralVisit::COOKIE, $original->token)
            ->get('/r/newcode1')
            ->assertRedirect(route('register'));

        $this->assertSame(2, AffiliateReferralVisit::query()->count());
        $this->assertFalse($original->fresh()->isOpen());

        $replacementVisit = AffiliateReferralVisit::query()
            ->where('affiliate_account_id', $replacement->id)
            ->firstOrFail();
        $this->assertTrue($replacementVisit->isOpen());

        $owner = User::factory()->create();
        $this->onboard($owner, 'replaced-church', $replacementVisit->token);

        $attribution = $this->attributionFor('replaced-church');
        $this->assertSame($replacement->id, $attribution->affiliate_account_id);
        $this->assertSame(AffiliateAttributionSource::FirstTouch, $attribution->source);
        $this->assertNull($original->fresh()->organization_id);
    }

    public function test_organization_created_inside_the_window_attaches_to_the_affiliate(): void
    {
        $affiliate = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'convcode');
        $this->get('/r/convcode');
        $token = AffiliateReferralVisit::query()->firstOrFail()->token;

        $owner = User::factory()->create();
        $this->onboard($owner, 'converted-church', $token);

        $organization = Organization::query()->where('slug', 'converted-church')->firstOrFail();
        $attribution = $organization->affiliateAttribution;
        $this->assertNotNull($attribution);
        $this->assertSame($affiliate->id, $attribution->affiliate_account_id);
        $this->assertSame(AffiliateAttributionSource::FirstTouch, $attribution->source);
        $this->assertTrue($attribution->isEarningEligible());
        $this->assertSame($organization->id, AffiliateReferralVisit::query()->firstOrFail()->organization_id);
        $this->assertSame(0, PaymentCheckout::query()->count());
        $this->assertTrue(Schema::hasTable('affiliate_commissions'));
        $this->assertTrue(Schema::hasTable('affiliate_payouts'));
        $this->assertFalse(Schema::hasTable('organization_payments'));
    }

    public function test_later_referral_links_do_not_replace_an_attached_organization(): void
    {
        $first = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'stuckcod');
        $second = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'newstuck');

        $this->get('/r/stuckcod');
        $token = AffiliateReferralVisit::query()->firstOrFail()->token;
        $owner = User::factory()->create();
        $this->onboard($owner, 'stuck-church', $token);

        $this->actingAs($owner)
            ->get('/r/newstuck')
            ->assertRedirect(route('creator.home'));

        $organization = Organization::query()->where('slug', 'stuck-church')->firstOrFail();
        $this->assertSame($first->id, $organization->affiliateAttribution->affiliate_account_id);
        $this->assertSame(1, AffiliateAttribution::query()->where('organization_id', $organization->id)->count());

        $secondVisit = AffiliateReferralVisit::query()->where('affiliate_account_id', $second->id)->firstOrFail();
        $request = Request::create('/', 'POST', [], [
            AffiliateReferralVisit::COOKIE => $secondVisit->token,
        ]);
        $again = app(AffiliateAttributionService::class)->attachNewOrganization($organization, $request);

        $this->assertSame($first->id, $again->affiliate_account_id);
        $this->assertSame(1, AffiliateAttributionChange::query()->where('organization_id', $organization->id)->count());
    }

    public function test_signup_without_a_referral_stays_organic(): void
    {
        $owner = User::factory()->create();
        $this->onboard($owner, 'organic-church');

        $attribution = $this->attributionFor('organic-church');
        $this->assertSame(AffiliateAttributionSource::Organic, $attribution->source);
        $this->assertNull($attribution->affiliate_account_id);
        $this->assertFalse($attribution->isEarningEligible());
        $this->assertSame(0, AffiliateReferralVisit::query()->count());
        $this->assertSame(0, PaymentCheckout::query()->count());
    }

    public function test_self_referral_is_recorded_and_is_not_earning_eligible(): void
    {
        $owner = User::factory()->create();
        $affiliate = $this->makeAffiliate($owner, AffiliateStatus::Approved, 'owncode1');

        $this->get('/r/owncode1');
        $token = AffiliateReferralVisit::query()->firstOrFail()->token;
        $this->onboard($owner, 'own-church', $token);

        $attribution = $this->attributionFor('own-church');
        $this->assertSame(AffiliateAttributionSource::BlockedSelfReferral, $attribution->source);
        $this->assertSame($affiliate->id, $attribution->affiliate_account_id);
        $this->assertFalse($attribution->isEarningEligible());
        $this->assertSame(OrgRole::Owner, $owner->fresh()->orgRole($attribution->organization));
        $this->assertSame(0, PaymentCheckout::query()->count());

        $member = User::factory()->create();
        $memberAffiliate = $this->makeAffiliate($member, AffiliateStatus::Approved, 'membercd');
        $memberOrg = $this->makeOrganization('member-church');
        $memberOrg->users()->attach(User::factory()->create()->id, ['role' => OrgRole::Owner->value]);
        $memberOrg->users()->attach($member->id, ['role' => OrgRole::Member->value]);
        $memberVisit = AffiliateReferralVisit::query()->create([
            'affiliate_account_id' => $memberAffiliate->id,
            'referral_code' => $memberAffiliate->referral_code,
            'token' => 'member-token',
            'first_seen_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
        $memberAttribution = app(AffiliateAttributionService::class)->attachNewOrganization(
            $memberOrg,
            Request::create('/', 'POST', [], [AffiliateReferralVisit::COOKIE => $memberVisit->token]),
        );
        $this->assertSame(AffiliateAttributionSource::FirstTouch, $memberAttribution->source);
        $this->assertTrue($memberAttribution->isEarningEligible());

        $controller = User::factory()->create();
        $controllerAffiliate = $this->makeAffiliate($controller, AffiliateStatus::Approved, 'ctrlcode');
        $controlled = $this->makeOrganization('controlled-church');
        $controlled->users()->attach(User::factory()->create()->id, ['role' => OrgRole::Owner->value]);
        $controlled->users()->attach($controller->id, ['role' => OrgRole::Admin->value]);
        $controlledVisit = AffiliateReferralVisit::query()->create([
            'affiliate_account_id' => $controllerAffiliate->id,
            'referral_code' => $controllerAffiliate->referral_code,
            'token' => 'controller-token',
            'first_seen_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
        $blocked = app(AffiliateAttributionService::class)->attachNewOrganization(
            $controlled,
            Request::create('/', 'POST', [], [AffiliateReferralVisit::COOKIE => $controlledVisit->token]),
        );
        $this->assertSame(AffiliateAttributionSource::BlockedSelfReferral, $blocked->source);
        $this->assertFalse($blocked->isEarningEligible());
    }

    public function test_admin_reassignment_requires_a_reason_and_keeps_history(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $this->onboard($owner, 'reassign-church');
        $organization = Organization::query()->where('slug', 'reassign-church')->firstOrFail();

        $first = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'assign01');
        $second = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'assign02');

        $this->actingAs($admin)
            ->put(route('admin.organizations.attribution', $organization), [
                'affiliate_account_id' => $first->id,
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(AffiliateAttributionSource::Organic, $organization->affiliateAttribution->fresh()->source);
        $this->assertSame(1, AffiliateAttributionChange::query()->where('organization_id', $organization->id)->count());

        $this->actingAs($admin)
            ->put(route('admin.organizations.attribution', $organization), [
                'affiliate_account_id' => $first->id,
                'reason' => 'Agreed introduction from the Lagos team.',
            ])
            ->assertRedirect(route('admin.organizations.edit', $organization));

        $attribution = $organization->affiliateAttribution->fresh();
        $this->assertSame(AffiliateAttributionSource::Admin, $attribution->source);
        $this->assertSame($first->id, $attribution->affiliate_account_id);
        $this->assertTrue($attribution->isEarningEligible());

        $this->actingAs($admin)
            ->put(route('admin.organizations.attribution', $organization), [
                'affiliate_account_id' => $second->id,
                'reason' => 'Corrected after a duplicate introduction.',
            ])
            ->assertRedirect(route('admin.organizations.edit', $organization));

        $changes = AffiliateAttributionChange::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(3, $changes);
        $this->assertSame(AffiliateAttributionSource::Organic, $changes[0]->new_source);
        $this->assertSame($first->id, $changes[1]->new_affiliate_account_id);
        $this->assertSame($admin->id, $changes[1]->changed_by);
        $this->assertSame('Agreed introduction from the Lagos team.', $changes[1]->reason);
        $this->assertSame($first->id, $changes[2]->previous_affiliate_account_id);
        $this->assertSame($second->id, $changes[2]->new_affiliate_account_id);
        $this->assertSame('Corrected after a duplicate introduction.', $changes[2]->reason);
        $this->assertSame($second->id, $organization->affiliateAttribution->fresh()->affiliate_account_id);

        $this->actingAs($admin)
            ->get(route('admin.organizations.edit', $organization))
            ->assertOk()
            ->assertSee('Agreed introduction from the Lagos team.')
            ->assertSee('Corrected after a duplicate introduction.')
            ->assertSee('assign02');

        $ownerAffiliate = $this->makeAffiliate($owner, AffiliateStatus::Approved, 'owneraf1');
        $this->actingAs($admin)
            ->put(route('admin.organizations.attribution', $organization), [
                'affiliate_account_id' => $ownerAffiliate->id,
                'reason' => 'Owner cannot earn on their own channel.',
            ])
            ->assertRedirect(route('admin.organizations.edit', $organization));

        $blocked = $organization->affiliateAttribution->fresh();
        $this->assertSame(AffiliateAttributionSource::BlockedSelfReferral, $blocked->source);
        $this->assertSame($ownerAffiliate->id, $blocked->affiliate_account_id);
        $this->assertFalse($blocked->isEarningEligible());
        $this->assertDatabaseHas('affiliate_attribution_changes', [
            'organization_id' => $organization->id,
            'new_affiliate_account_id' => $ownerAffiliate->id,
            'new_source' => AffiliateAttributionSource::BlockedSelfReferral->value,
            'reason' => 'Owner cannot earn on their own channel.',
            'changed_by' => $admin->id,
        ]);
        $this->assertSame(0, PaymentCheckout::query()->count());
    }

    public function test_suspension_blocks_new_attribution_without_changing_history(): void
    {
        $admin = User::factory()->admin()->create();
        $affiliateUser = User::factory()->create();
        $affiliate = $this->makeAffiliate($affiliateUser, AffiliateStatus::Approved, 'suspcode');

        $this->get('/r/suspcode');
        $token = AffiliateReferralVisit::query()->firstOrFail()->token;
        $owner = User::factory()->create();
        $this->onboard($owner, 'suspended-church', $token);

        $organization = Organization::query()->where('slug', 'suspended-church')->firstOrFail();
        $before = $organization->affiliateAttribution->fresh();
        $this->assertSame($affiliate->id, $before->affiliate_account_id);
        $this->assertSame(AffiliateAttributionSource::FirstTouch, $before->source);
        $history = AffiliateAttributionChange::query()->where('organization_id', $organization->id)->count();
        $visits = AffiliateReferralVisit::query()->count();

        $this->actingAs($admin)
            ->post(route('admin.affiliates.suspend', $affiliate))
            ->assertRedirect();

        $affiliate->refresh();
        $this->assertSame(AffiliateStatus::Suspended, $affiliate->status);
        $this->assertNotNull($affiliate->suspended_at);
        $this->assertNotNull($affiliate->approved_at);

        $after = $organization->affiliateAttribution->fresh();
        $this->assertSame($before->affiliate_account_id, $after->affiliate_account_id);
        $this->assertSame($before->source, $after->source);
        $this->assertSame($history, AffiliateAttributionChange::query()->where('organization_id', $organization->id)->count());

        $this->actingAsGuest()->get('/r/suspcode')->assertRedirect(route('register'));
        $this->assertSame($visits, AffiliateReferralVisit::query()->count());

        $otherOwner = User::factory()->create();
        $this->onboard($otherOwner, 'after-suspend');
        $other = Organization::query()->where('slug', 'after-suspend')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.organizations.edit', $other))
            ->put(route('admin.organizations.attribution', $other), [
                'affiliate_account_id' => $affiliate->id,
                'reason' => 'Trying to attach a suspended affiliate.',
            ])
            ->assertSessionHasErrors('affiliate_account_id');

        $this->assertSame(AffiliateAttributionSource::Organic, $other->affiliateAttribution->fresh()->source);
        $this->assertSame($history, AffiliateAttributionChange::query()->where('organization_id', $organization->id)->count());
    }

    public function test_admin_can_manage_affiliate_identity_without_granting_church_powers(): void
    {
        $admin = User::factory()->admin()->create();
        $person = User::factory()->create(['email' => 'affiliate@example.org']);
        $churchOwner = User::factory()->create();
        $church = $this->makeOrganization('managed-church');
        $church->users()->attach($churchOwner->id, ['role' => OrgRole::Owner->value]);

        $this->actingAs($churchOwner)
            ->get(route('admin.affiliates.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.affiliates.store'), [
                'email' => 'affiliate@example.org',
                'status' => AffiliateStatus::Pending->value,
                'country_code' => 'ng',
                'state_region' => 'Lagos',
                'city' => 'Ikeja',
            ])
            ->assertRedirect();

        $affiliate = AffiliateAccount::query()->where('user_id', $person->id)->firstOrFail();
        $this->assertSame(AffiliateStatus::Pending, $affiliate->status);
        $this->assertSame('NG', $affiliate->country_code);
        $this->assertNull($affiliate->payout_qualified_at);
        $this->assertSame(OrgRole::Owner, $churchOwner->fresh()->orgRole($church));

        $this->actingAs($admin)
            ->get(route('admin.affiliates.index'))
            ->assertOk()
            ->assertSee($affiliate->referral_code)
            ->assertSee('Ikeja');

        $this->actingAs($admin)
            ->get(route('admin.affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee($affiliate->referral_code)
            ->assertSee('Pending')
            ->assertSee('Approve');

        $this->actingAs($admin)
            ->post(route('admin.affiliates.approve', $affiliate))
            ->assertRedirect();
        $this->assertSame(AffiliateStatus::Approved, $affiliate->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.affiliates.location', $affiliate), [
                'country_code' => 'GH',
                'state_region' => 'Greater Accra',
                'city' => 'Accra',
            ])
            ->assertRedirect();

        $affiliate->refresh();
        $this->assertSame('GH', $affiliate->country_code);
        $this->assertSame('Greater Accra', $affiliate->state_region);
        $this->assertSame('Accra', $affiliate->city);
        $this->assertSame($affiliate->referral_code, $affiliate->fresh()->referral_code);

        $this->actingAs($admin)->post(route('admin.affiliates.suspend', $affiliate))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.affiliates.restore', $affiliate))->assertRedirect();
        $this->assertSame(AffiliateStatus::Approved, $affiliate->fresh()->status);
        $this->assertNull($affiliate->fresh()->suspended_at);

        $pending = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Pending, 'rejectme');
        $this->actingAs($admin)->post(route('admin.affiliates.reject', $pending))->assertRedirect();
        $this->assertSame(AffiliateStatus::Rejected, $pending->fresh()->status);

        $stream = Stream::query()->create([
            'organization_id' => $church->id,
            'uuid' => fake()->uuid(),
            'title' => 'Live',
            'status' => StreamStatus::Offline,
            'is_public' => true,
        ]);
        $church->users()->attach($person->id, ['role' => OrgRole::Member->value]);

        $this->actingAs($person)
            ->get(route('admin.streams.studio', $stream))
            ->assertForbidden();

        $this->actingAs($person->fresh())
            ->get(route('admin.affiliates.index'))
            ->assertForbidden();

        $outsider = User::factory()->create();
        $this->makeAffiliate($outsider, AffiliateStatus::Approved, 'outsider');
        $this->actingAs($outsider)
            ->get(route('billing.plans'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_programme_settings_keep_defaults_and_can_be_overridden(): void
    {
        $this->assertTrue(AffiliateProgrammeSettings::enabled());
        $this->assertSame(AffiliateProgrammeSettings::REGISTRATION_FREE, AffiliateProgrammeSettings::registrationMode());
        $this->assertSame(90, AffiliateProgrammeSettings::attributionDays());
        $this->assertSame(5, AffiliateProgrammeSettings::all()['payout_qualification_target']);
        $this->assertSame(30, AffiliateProgrammeSettings::all()['prospect_reservation_days']);
        $this->assertSame(20, AffiliateProgrammeSettings::all()['prospect_active_limit']);
        $this->assertSame(1000, AffiliateProgrammeSettings::all()['default_commission_bps']);
        $this->assertSame(14, AffiliateProgrammeSettings::all()['commission_hold_days']);
        $this->assertSame(9000, AffiliateProgrammeSettings::all()['retention_waiver_bps']);
        $this->assertSame(1, AffiliateProgrammeSettings::all()['inactivity_warning_months']);
        $this->assertSame(3, AffiliateProgrammeSettings::all()['penalty_first_months']);
        $this->assertSame(700, AffiliateProgrammeSettings::all()['penalty_first_bps']);
        $this->assertSame(6, AffiliateProgrammeSettings::all()['penalty_second_months']);
        $this->assertSame(500, AffiliateProgrammeSettings::all()['penalty_second_bps']);

        SiteSetting::putValue(AffiliateProgrammeSettings::KEY, json_encode([
            'enabled' => false,
            'registration_mode' => AffiliateProgrammeSettings::REGISTRATION_PAID,
            'attribution_days' => 10,
        ]));

        $this->assertFalse(AffiliateProgrammeSettings::enabled());
        $this->assertSame(AffiliateProgrammeSettings::REGISTRATION_PAID, AffiliateProgrammeSettings::registrationMode());
        $this->assertSame(10, AffiliateProgrammeSettings::attributionDays());
        $this->assertSame(1000, AffiliateProgrammeSettings::all()['default_commission_bps']);
        $this->assertSame(0, PaymentCheckout::query()->count());

        $affiliate = $this->makeAffiliate(User::factory()->create(), AffiliateStatus::Approved, 'offcode1');
        $this->get('/r/'.$affiliate->referral_code)->assertRedirect(route('register'));
        $this->assertSame(0, AffiliateReferralVisit::query()->count());
    }

    private function makeAffiliate(User $user, AffiliateStatus $status, string $code): AffiliateAccount
    {
        return AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'referral_code' => $code,
            'status' => $status,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'approved_at' => $status === AffiliateStatus::Approved ? now() : null,
        ]);
    }

    private function makeOrganization(string $slug): Organization
    {
        return Organization::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'is_public' => true,
        ]);
    }

    private function onboard(User $user, string $slug, ?string $token = null): void
    {
        $this->actingAs($user)
            ->post(route('onboarding.type'), ['creator_type' => CreatorType::Church->value])
            ->assertRedirect(route('onboarding.channel'));

        $request = $this->actingAs($user);
        if ($token !== null) {
            $request = $request->withCookie(AffiliateReferralVisit::COOKIE, $token);
        }

        $request->post(route('onboarding.channel.store'), [
            'name' => 'Grace Chapel',
            'slug' => $slug,
            'theme_color' => '#3d9b7a',
        ])->assertRedirect(route('creator.home'));
    }

    private function attributionFor(string $slug): AffiliateAttribution
    {
        return Organization::query()->where('slug', $slug)->firstOrFail()->affiliateAttribution()->firstOrFail();
    }
}
