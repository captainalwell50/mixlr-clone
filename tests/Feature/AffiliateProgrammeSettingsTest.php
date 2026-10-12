<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\OrgRole;
use App\Models\AffiliateAccount;
use App\Models\AffiliateSettingAudit;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AffiliateProgrammeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_only_a_platform_admin_can_open_programme_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create();
        $affiliateUser = User::factory()->create();
        $church = Organization::query()->create([
            'name' => 'Managed Chapel',
            'slug' => 'managed-chapel',
            'is_public' => true,
        ]);
        $church->users()->attach($manager->id, ['role' => OrgRole::Owner->value]);
        $church->users()->attach($affiliateUser->id, ['role' => OrgRole::Member->value]);
        AffiliateAccount::query()->create([
            'user_id' => $affiliateUser->id,
            'referral_code' => 'affonly1',
            'status' => AffiliateStatus::Approved,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'approved_at' => now(),
        ]);

        foreach (['admin.affiliate-programme.overview', 'admin.affiliate-programme.settings', 'admin.affiliate-programme.levels', 'admin.affiliate-programme.audit'] as $route) {
            $this->actingAs($manager)->get(route($route))->assertForbidden();
            $this->actingAs($affiliateUser)->get(route($route))->assertForbidden();
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $this->actingAs($admin)
            ->get(route('admin.affiliate-programme.settings'))
            ->assertSee('Self-referral protection')
            ->assertSee('Actual amount collected')
            ->assertSee('Paystack Transfer is disabled')
            ->assertSee('do not rewrite history');
    }

    public function test_saving_settings_audits_the_change_and_does_not_create_commissions(): void
    {
        $admin = User::factory()->admin()->create();
        $approved = $this->affiliate(AffiliateStatus::Approved, 'stayappr');

        $this->actingAs($admin)
            ->put(route('admin.affiliate-programme.settings.update'), $this->payload([
                'registration_mode' => AffiliateProgrammeSettings::REGISTRATION_PAID,
                'registration_fee_minor' => '2500',
                'default_commission_bps' => '12',
                'attribution_days' => '60',
                'prospect_reservation_days' => '21',
                'payout_qualification_target' => '8',
            ]))
            ->assertRedirect(route('admin.affiliate-programme.settings'))
            ->assertSessionHasNoErrors();

        $this->assertSame(AffiliateProgrammeSettings::REGISTRATION_PAID, AffiliateProgrammeSettings::registrationMode());
        $this->assertSame(250000, AffiliateProgrammeSettings::all()['registration_fee_minor']);
        $this->assertSame(1200, AffiliateProgrammeSettings::all()['default_commission_bps']);
        $this->assertSame(60, AffiliateProgrammeSettings::attributionDays());
        $this->assertSame(21, AffiliateProgrammeSettings::prospectReservationDays());
        $this->assertSame(8, AffiliateProgrammeSettings::all()['payout_qualification_target']);
        $this->assertTrue(AffiliateProgrammeSettings::all()['self_referral_protection']);
        $this->assertSame('manual', AffiliateProgrammeSettings::all()['payout_mode']);
        $this->assertSame('collected', AffiliateProgrammeSettings::all()['commission_basis']);
        $this->assertFalse(AffiliateProgrammeSettings::all()['reservation_renewal_enabled']);
        $this->assertSame(AffiliateStatus::Approved, $approved->fresh()->status);
        $this->assertSame(0, PaymentCheckout::query()->count());
        $this->assertTrue(Schema::hasTable('affiliate_commissions'));
        $this->assertTrue(Schema::hasTable('affiliate_payouts'));
        $this->assertFalse(Schema::hasTable('organization_payments'));

        $rate = AffiliateSettingAudit::query()->where('setting_key', 'default_commission_bps')->firstOrFail();
        $this->assertSame($admin->id, $rate->actor_id);
        $this->assertSame('1000', json_decode((string) $rate->old_value, true) === 1000 ? '1000' : (string) json_decode((string) $rate->old_value));
        $this->assertSame(1000, json_decode((string) $rate->old_value, true));
        $this->assertSame(1200, json_decode((string) $rate->new_value, true));

        $this->actingAs($admin)
            ->get(route('admin.affiliate-programme.audit'))
            ->assertOk()
            ->assertSee('Default commission rate')
            ->assertSee('12%')
            ->assertSee($admin->name);
    }

    public function test_paystack_transfer_and_self_referral_cannot_be_turned_on_or_off(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.affiliate-programme.settings.update'), $this->payload([
                'payout_mode' => 'paystack',
            ]))
            ->assertSessionHasErrors('payout_mode');

        $this->assertSame('manual', AffiliateProgrammeSettings::all()['payout_mode']);
        $this->assertSame(0, AffiliateSettingAudit::query()->count());

        SiteSetting::putValue(AffiliateProgrammeSettings::KEY, json_encode([
            'self_referral_protection' => false,
            'payout_mode' => 'paystack',
            'reservation_renewal_enabled' => true,
        ]));

        $this->assertTrue(AffiliateProgrammeSettings::all()['self_referral_protection']);
        $this->assertSame('manual', AffiliateProgrammeSettings::all()['payout_mode']);
        $this->assertFalse(AffiliateProgrammeSettings::all()['reservation_renewal_enabled']);
    }

    public function test_closed_registration_and_the_approved_cap_do_not_remove_current_affiliates(): void
    {
        $admin = User::factory()->admin()->create();
        $approved = $this->affiliate(AffiliateStatus::Approved, 'capkeep1');
        $pendingUser = User::factory()->create(['email' => 'pending@example.test']);
        $pending = $this->affiliate(AffiliateStatus::Pending, 'capwait1', $pendingUser);

        $this->actingAs($admin)
            ->put(route('admin.affiliate-programme.settings.update'), $this->payload([
                'registration_mode' => AffiliateProgrammeSettings::REGISTRATION_CLOSED,
                'max_approved_affiliates' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.affiliates.store'), [
                'email' => User::factory()->create(['email' => 'new@example.test'])->email,
                'status' => AffiliateStatus::Pending->value,
                'country_code' => 'NG',
                'state_region' => 'Lagos',
                'city' => 'Ikeja',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post(route('admin.affiliates.approve', $pending))
            ->assertSessionHas('error');

        $this->assertSame(AffiliateStatus::Approved, $approved->fresh()->status);
        $this->assertSame(AffiliateStatus::Pending, $pending->fresh()->status);
    }

    public function test_a_disabled_outreach_method_cannot_be_planned(): void
    {
        $admin = User::factory()->admin()->create();
        $affiliate = $this->affiliate(AffiliateStatus::Approved, 'novisit1');

        $this->actingAs($admin)
            ->put(route('admin.affiliate-programme.settings.update'), $this->payload([
                'outreach_visit_enabled' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), [
                'church_name' => 'Cedar Assembly',
                'country_code' => 'NG',
                'state_region' => 'Lagos',
                'city' => 'Ikeja',
                'outreach_method' => 'visit',
            ])
            ->assertSessionHasErrors('outreach_method');

        $this->actingAs($affiliate->user)
            ->post(route('affiliate.prospects.store'), [
                'church_name' => 'Cedar Assembly',
                'country_code' => 'NG',
                'state_region' => 'Lagos',
                'city' => 'Ikeja',
                'outreach_method' => 'phone_call',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_level_threshold_changes_are_audited_without_assigning_anyone(): void
    {
        $admin = User::factory()->admin()->create();
        $levels = AffiliateProgrammeSettings::defaultLevels();
        $levels[1]['min_active_paying_churches'] = 6;

        $this->actingAs($admin)
            ->put(route('admin.affiliate-programme.levels.update'), ['levels' => $levels])
            ->assertRedirect(route('admin.affiliate-programme.levels'))
            ->assertSessionHasNoErrors();

        $this->assertSame(6, AffiliateProgrammeSettings::levels()[1]['min_active_paying_churches']);
        $audit = AffiliateSettingAudit::query()->where('setting_key', 'levels')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame(5, json_decode((string) $audit->old_value, true)[1]['min_active_paying_churches']);
        $this->assertSame(6, json_decode((string) $audit->new_value, true)[1]['min_active_paying_churches']);
        $this->assertTrue(Schema::hasTable('affiliate_commissions'));

        $this->actingAs($admin)
            ->get(route('admin.affiliate-programme.overview'))
            ->assertOk()
            ->assertSee('Not assigned')
            ->assertSee('Not recorded');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $payload = [];
        foreach (AffiliateProgrammeSettings::all() as $key => $value) {
            if (str_ends_with($key, '_bps') || str_ends_with($key, '_minor')) {
                $payload[$key] = ((int) $value) / 100;
            } elseif (is_bool($value)) {
                $payload[$key] = $value ? '1' : '0';
            } else {
                $payload[$key] = $value;
            }
        }

        return array_merge($payload, $overrides);
    }

    private function affiliate(AffiliateStatus $status, string $code, ?User $user = null): AffiliateAccount
    {
        $user ??= User::factory()->create();

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
}
