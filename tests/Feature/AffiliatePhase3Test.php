<?php

namespace Tests\Feature;

use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Enums\AffiliateStatus;
use App\Events\AffiliateCommissionPayable;
use App\Events\AffiliateCommissionReversed;
use App\Events\AffiliatePayoutApproved;
use App\Events\AffiliatePayoutPaid;
use App\Events\AffiliateQualificationAchieved;
use App\Mail\AffiliateCommissionReversedMail;
use App\Mail\AffiliatePayoutApprovedMail;
use App\Mail\AffiliatePayoutPaidMail;
use App\Mail\AffiliateQualifiedMail;
use App\Models\AffiliateAccount;
use App\Models\AffiliateAttribution;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAudit;
use App\Models\AffiliatePayout;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AffiliatePhase3Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $affiliateUser;
    protected AffiliateAccount $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin_phase3@example.com',
            'is_admin' => true,
        ]);

        $this->affiliateUser = User::factory()->create([
            'email' => 'partner_phase3@example.com',
            'name' => 'Pastor Emmanuel',
        ]);

        $this->affiliate = AffiliateAccount::query()->create([
            'user_id' => $this->affiliateUser->id,
            'referral_code' => 'PASTOR_EMM',
            'status' => AffiliateStatus::Approved,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'approved_at' => now(),
        ]);
    }

    protected function createOrganization(string $slug, string $name = 'Church'): Organization
    {
        return Organization::query()->create([
            'name' => $name.' '.ucfirst($slug),
            'slug' => $slug,
            'is_public' => true,
        ]);
    }

    public function test_affiliate_dashboard_requires_authenticated_active_affiliate(): void
    {
        $this->get(route('affiliate.dashboard'))
            ->assertRedirect(route('login'));

        $regularUser = User::factory()->create();
        $this->actingAs($regularUser)
            ->get(route('affiliate.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->affiliateUser)
            ->get(route('affiliate.dashboard'))
            ->assertOk()
            ->assertSee('PASTOR_EMM')
            ->assertSee('Partner Dashboard')
            ->assertSee('0 of 5 paying organisations');
    }

    public function test_affiliate_dashboard_shows_qualification_progress_and_permanent_qualified_state(): void
    {
        // Unqualified state
        $response = $this->actingAs($this->affiliateUser)
            ->get(route('affiliate.dashboard'));

        $response->assertOk();
        $response->assertSee('0 of 5 paying organisations');
        $response->assertSee('5 more needed');

        // Qualify affiliate
        $this->affiliate->update([
            'payout_qualified_at' => now()->subDay(),
        ]);
        $this->affiliateUser->unsetRelation('affiliateAccount');

        $responseQualified = $this->actingAs($this->affiliateUser)
            ->get(route('affiliate.dashboard'));

        $responseQualified->assertOk();
        $responseQualified->assertSee('Qualified for Payouts (Permanent)');
    }

    public function test_affiliate_dashboard_isolates_data_between_affiliates(): void
    {
        $otherUser = User::factory()->create(['email' => 'other_aff@example.com']);
        $otherAffiliate = AffiliateAccount::query()->create([
            'user_id' => $otherUser->id,
            'referral_code' => 'OTHER_CODE',
            'status' => AffiliateStatus::Approved,
            'country_code' => 'NG',
            'state_region' => 'Abuja',
            'city' => 'Garki',
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->affiliateUser)
            ->get(route('affiliate.dashboard'));

        $response->assertOk();
        $response->assertSee('PASTOR_EMM');
        $response->assertDontSee('OTHER_CODE');
    }

    public function test_affiliate_payouts_index_and_request_workflow(): void
    {
        $service = app(AffiliateCommissionService::class);

        // Initially unqualified and 0 balance
        $this->actingAs($this->affiliateUser)
            ->get(route('affiliate.payouts.index'))
            ->assertOk()
            ->assertSee('Qualification required');

        // Cannot request payout when unqualified
        $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.request'), [
                'bank_name' => 'Zenith Bank',
                'account_name' => 'Pastor Emmanuel',
                'account_number' => '0123456789',
            ])
            ->assertSessionHasErrors(['qualification']);

        // Qualify affiliate
        $this->affiliate->update(['payout_qualified_at' => now()]);
        $this->affiliateUser->unsetRelation('affiliateAccount');

        // Still 0 payable balance
        $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.request'))
            ->assertSessionHasErrors(['amount']);

        // Create a matured payable commission
        $org = $this->createOrganization('grace-assembly', 'Grace Assembly');
        $comm = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_ph3_req_1',
            'idempotency_key' => 'idemp_ph3_req_1',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 10000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 1000000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        // Request payout successfully
        $response = $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.request'), [
                'bank_name' => 'GTBank',
                'account_name' => 'Pastor Emmanuel',
                'account_number' => '0123456789',
                'notes' => 'Settlement request note',
            ]);

        $response->assertRedirect(route('affiliate.payouts.index'));
        $response->assertSessionHas('status');

        $payout = AffiliatePayout::query()->where('affiliate_account_id', $this->affiliate->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(AffiliatePayoutStatus::Draft, $payout->status);
        $this->assertEquals(1000000, $payout->amount_minor);
        $this->assertEquals('GTBank', $payout->bank_name);
        $this->assertEquals('••••6789', $payout->maskedAccountNumber());
        $this->assertStringContainsString('Settlement request note', $payout->notes);

        // Commission is locked to this payout batch
        $comm->refresh();
        $this->assertEquals($payout->id, $comm->affiliate_payout_id);

        // Cannot submit a duplicate payout request because payable balance is now 0
        $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.request'))
            ->assertSessionHasErrors(['amount']);
    }

    public function test_affiliate_commissions_statement_filtering(): void
    {
        $org = $this->createOrganization('lagos-city-church', 'Lagos City Church');

        AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_held_comm',
            'idempotency_key' => 'idemp_held_comm',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 5000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 500000,
            'status' => AffiliateCommissionStatus::Held,
            'confirmed_at' => now(),
            'hold_until' => now()->addDays(14),
        ]);

        AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_payable_comm',
            'idempotency_key' => 'idemp_payable_comm',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 8000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 800000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        // Filter by held
        $this->actingAs($this->affiliateUser)
            ->get(route('affiliate.commissions.index', ['status' => 'held']))
            ->assertOk()
            ->assertSee('pay_held_comm')
            ->assertDontSee('pay_payable_comm');

        // Filter by payable
        $this->actingAs($this->affiliateUser)
            ->get(route('affiliate.commissions.index', ['status' => 'payable']))
            ->assertOk()
            ->assertSee('pay_payable_comm')
            ->assertDontSee('pay_held_comm');
    }

    public function test_admin_affiliates_filtering_and_risk_indicators(): void
    {
        // Add a blocked self referral attribution to trigger risk flag
        $selfOrg = $this->createOrganization('pastor-own-church', 'Pastor Own Church');
        AffiliateAttribution::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $selfOrg->id,
            'source' => 'blocked_self_referral',
            'attributed_at' => now(),
            'notes' => 'Self referral blocked',
        ]);

        // Admin index with filters
        $this->actingAs($this->admin)
            ->get(route('admin.affiliates.index', ['q' => 'Emmanuel']))
            ->assertOk()
            ->assertSee('PASTOR_EMM')
            ->assertSee('Pastor Emmanuel');

        $this->actingAs($this->admin)
            ->get(route('admin.affiliates.index', ['q' => 'NonExistent']))
            ->assertOk()
            ->assertSee('No affiliates found matching the selected filter criteria.');

        // Admin show with risk indicators
        $response = $this->actingAs($this->admin)
            ->get(route('admin.affiliates.show', $this->affiliate));

        $response->assertOk();
        $response->assertSee('Risk Indicators &amp; Integrity Review', false);
        $response->assertSee('self-referral attempt(s) blocked');
        $response->assertSee('Pastor Own Church');
        $response->assertSee('Referred Organisations Breakdown');
    }

    public function test_admin_batch_payout_operations(): void
    {
        $service = app(AffiliateCommissionService::class);

        $this->affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('batch-org', 'Batch Org');

        // Create payable commission
        AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_batch_1',
            'idempotency_key' => 'idemp_batch_1',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 10000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 1000000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        // 1. Batch create
        $this->actingAs($this->admin)
            ->post(route('admin.affiliate-payouts.batch-create'), [
                'affiliate_ids' => [$this->affiliate->id],
            ])
            ->assertRedirect(route('admin.affiliate-payouts.index'))
            ->assertSessionHas('status');

        $payout = AffiliatePayout::query()->where('affiliate_account_id', $this->affiliate->id)->firstOrFail();
        $this->assertEquals(AffiliatePayoutStatus::Draft, $payout->status);

        // 2. Batch approve
        $this->actingAs($this->admin)
            ->post(route('admin.affiliate-payouts.batch-approve'), [
                'payout_ids' => [$payout->id],
            ])
            ->assertRedirect(route('admin.affiliate-payouts.index'))
            ->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals(AffiliatePayoutStatus::Approved, $payout->status);

        // 3. Batch mark paid
        $this->actingAs($this->admin)
            ->post(route('admin.affiliate-payouts.batch-mark-paid'), [
                'payout_ids' => [$payout->id],
                'payment_reference' => 'BANK_TRANSFER_BATCH_99',
            ])
            ->assertRedirect(route('admin.affiliate-payouts.index'))
            ->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals(AffiliatePayoutStatus::Paid, $payout->status);
        $this->assertNotNull($payout->paid_at);
        $this->assertEquals($this->admin->id, $payout->paid_by);
    }

    public function test_admin_operational_reporting_screen(): void
    {
        $this->get(route('admin.affiliate-reports.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($this->admin)
            ->get(route('admin.affiliate-reports.index'))
            ->assertOk()
            ->assertSee('Affiliate Operational')
            ->assertSee('Programme Balance Liabilities')
            ->assertSee('Top Performing Affiliates')
            ->assertSee('Referral click tracking not currently authoritative');
    }

    public function test_domain_events_and_email_notification_pipeline(): void
    {
        Mail::fake();

        $service = app(AffiliateCommissionService::class);

        // 1. Trigger qualification event
        event(new AffiliateQualificationAchieved($this->affiliate, 5));
        Mail::assertQueued(AffiliateQualifiedMail::class, function ($mail) {
            return $mail->hasTo($this->affiliateUser->email)
                && $mail->affiliateAccount->id === $this->affiliate->id;
        });

        // 2. Trigger payout approved event
        $payout = AffiliatePayout::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'payout_reference' => 'PO_EVT_TEST',
            'currency' => 'NGN',
            'amount_minor' => 500000,
            'status' => AffiliatePayoutStatus::Approved,
            'admin_id' => $this->admin->id,
            'approved_at' => now(),
        ]);
        event(new AffiliatePayoutApproved($payout));
        Mail::assertQueued(AffiliatePayoutApprovedMail::class, function ($mail) {
            return $mail->hasTo($this->affiliateUser->email);
        });

        // 3. Trigger payout paid event
        $payout->update([
            'status' => AffiliatePayoutStatus::Paid,
            'paid_at' => now(),
            'paid_by' => $this->admin->id,
        ]);
        event(new AffiliatePayoutPaid($payout));
        Mail::assertQueued(AffiliatePayoutPaidMail::class, function ($mail) {
            return $mail->hasTo($this->affiliateUser->email);
        });

        // 4. Trigger commission reversal event
        $org = $this->createOrganization('rev-org', 'Reversal Church');
        $commission = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_rev_evt',
            'idempotency_key' => 'idemp_rev_evt',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 1000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 100000,
            'status' => AffiliateCommissionStatus::Reversed,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDay(),
            'reversed_at' => now(),
            'reversal_reason' => 'Customer disputed charge',
        ]);
        event(new AffiliateCommissionReversed($commission, 'Customer disputed charge'));
        Mail::assertQueued(AffiliateCommissionReversedMail::class, function ($mail) {
            return $mail->hasTo($this->affiliateUser->email);
        });
    }

    public function test_default_zero_payout_threshold_allows_any_positive_payable_balance(): void
    {
        $this->affiliate->update([
            'payout_qualified_at' => now()->subDays(5),
            'bank_name' => 'Zenith Bank',
            'account_name' => 'Emmanuel Adebayo',
            'account_number' => '2001234567',
        ]);

        $org = $this->createOrganization('small-church', 'Small Church');
        // Very small commission: ₦150 (15,000 minor)
        $comm = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_small_comm',
            'idempotency_key' => 'idemp_small_comm',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 150000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 15000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        // Default settings has minimum_payout_minor = 0 (disabled)
        $this->assertSame(0, AffiliateProgrammeSettings::all()['minimum_payout_minor']);

        $response = $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.request'));

        $response->assertRedirect(route('affiliate.payouts.index'));
        $response->assertSessionHas('status');

        $payout = AffiliatePayout::query()->where('affiliate_account_id', $this->affiliate->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame(15000, $payout->amount_minor);
        $this->assertSame('Zenith Bank', $payout->bank_name);
        $this->assertSame('••••4567', $payout->maskedAccountNumber());
    }

    public function test_configurable_minimum_payout_threshold_enforces_limits_when_enabled(): void
    {
        $this->affiliate->update([
            'payout_qualified_at' => now()->subDays(5),
            'bank_name' => 'Zenith Bank',
            'account_name' => 'Emmanuel Adebayo',
            'account_number' => '2001234567',
        ]);

        // Configure ₦5,000 (500,000 minor) threshold
        \App\Models\SiteSetting::putValue(
            AffiliateProgrammeSettings::KEY,
            json_encode(array_merge(AffiliateProgrammeSettings::defaults(), [
                'minimum_payout_minor' => 500000,
            ]))
        );

        $org = $this->createOrganization('threshold-church', 'Threshold Church');

        // Commission below threshold: ₦2,000 (200,000 minor)
        $comm = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_thresh_comm',
            'idempotency_key' => 'idemp_thresh_comm',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 2000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 200000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.request'))
            ->assertSessionHasErrors(['amount']);

        // Add another commission to exceed the 500,000 minor threshold
        AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_thresh_comm_2',
            'idempotency_key' => 'idemp_thresh_comm_2',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 4000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 400000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        // Total payable is now 600,000 minor >= 500,000 minor
        $response = $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.request'));

        $response->assertRedirect(route('affiliate.payouts.index'));
        $response->assertSessionHas('status');

        $payout = AffiliatePayout::query()->where('affiliate_account_id', $this->affiliate->id)->first();
        $this->assertSame(600000, $payout->amount_minor);
    }

    public function test_bank_account_details_encrypted_at_rest_and_masked_in_presentations(): void
    {
        // 1. Submit bank destination update including bank_code
        $this->actingAs($this->affiliateUser)
            ->post(route('affiliate.payouts.destination'), [
                'bank_name' => 'First Bank of Nigeria',
                'bank_code' => '011',
                'account_name' => 'Pastor Emmanuel Lagos',
                'account_number' => '3012345678',
            ])
            ->assertRedirect(route('affiliate.payouts.index'))
            ->assertSessionHas('status');

        $this->affiliate->refresh();
        $this->assertSame('First Bank of Nigeria', $this->affiliate->bank_name);
        $this->assertSame('011', $this->affiliate->bank_code);
        $this->assertSame('Pastor Emmanuel Lagos', $this->affiliate->account_name);
        $this->assertSame('••••5678', $this->affiliate->maskedAccountNumber());
        $this->assertSame('First Bank of Nigeria (••••5678) - Pastor Emmanuel Lagos', $this->affiliate->payoutDestinationSummary());

        // 2. Verify raw columns (account_number AND account_name) in database are encrypted ciphertext, NOT plaintext
        $rawRow = \Illuminate\Support\Facades\DB::table('affiliate_accounts')->where('id', $this->affiliate->id)->first();
        $this->assertNotEquals('3012345678', $rawRow->account_number);
        $this->assertStringStartsWith('eyJ', $rawRow->account_number);
        $this->assertNotEquals('Pastor Emmanuel Lagos', $rawRow->account_name);
        $this->assertStringStartsWith('eyJ', $rawRow->account_name);
        $this->assertSame('First Bank of Nigeria', $rawRow->bank_name);
        $this->assertSame('011', $rawRow->bank_code);

        // 3. Verify both account_number and account_name are hidden from array/JSON serialization
        $serialized = $this->affiliate->toArray();
        $this->assertArrayNotHasKey('account_number', $serialized);
        $this->assertArrayNotHasKey('account_name', $serialized);
        $this->assertStringNotContainsString('3012345678', $this->affiliate->toJson());
        $this->assertStringNotContainsString('Pastor Emmanuel Lagos', $this->affiliate->toJson());

        // 4. Verify UI masking on affiliate payouts page (masked shown, full account number never rendered)
        $uiResponse = $this->actingAs($this->affiliateUser)->get(route('affiliate.payouts.index'));
        $uiResponse->assertOk();
        $uiResponse->assertSee('••••5678');
        $uiResponse->assertSee('First Bank of Nigeria');
        $uiResponse->assertDontSee('3012345678');

        // 5. Verify affiliate isolation: another affiliate sees only their own destination, never this affiliate's
        $secondUser = User::factory()->create(['email' => 'second_aff@example.com']);
        AffiliateAccount::query()->create([
            'user_id' => $secondUser->id,
            'referral_code' => 'SECOND_AFF',
            'status' => AffiliateStatus::Approved,
            'country_code' => 'NG',
            'state_region' => 'Abuja',
            'city' => 'Garki',
            'approved_at' => now(),
            'bank_name' => 'UBA',
            'bank_code' => '033',
            'account_name' => 'Second Affiliate Name',
            'account_number' => '2098765432',
        ]);

        $secondUi = $this->actingAs($secondUser)->get(route('affiliate.payouts.index'));
        $secondUi->assertOk();
        $secondUi->assertSee('••••5432');
        $secondUi->assertSee('Second Affiliate Name');
        $secondUi->assertDontSee('••••5678');
        $secondUi->assertDontSee('Pastor Emmanuel Lagos');
        $secondUi->assertDontSee('First Bank of Nigeria');

        // 6. Verify unauthorized user cannot edit payout destination
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->post(route('affiliate.payouts.destination'), [
                'bank_name' => 'Hacker Bank',
                'account_name' => 'Hacker',
                'account_number' => '9999999999',
            ])
            ->assertForbidden();
    }

    public function test_payout_destination_snapshot_immutability_and_audit_email_safety(): void
    {
        $service = app(AffiliateCommissionService::class);

        $this->affiliate->update([
            'payout_qualified_at' => now()->subDays(10),
            'bank_name' => 'Access Bank',
            'bank_code' => '044',
            'account_name' => 'Original Church Ministry',
            'account_number' => '0011223344',
        ]);

        $org = $this->createOrganization('snapshot-church', 'Snapshot Church');
        $comm = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_snap_1',
            'idempotency_key' => 'idemp_snap_1',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 5000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 500000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        // 1. Create payout and verify all 4 destination fields are snapshotted and encrypted at rest
        $payout = $service->requestPayout($this->affiliate, 'NGN', 'Regular settlement');

        $this->assertSame('Access Bank', $payout->bank_name);
        $this->assertSame('044', $payout->bank_code);
        $this->assertSame('Original Church Ministry', $payout->account_name);
        $this->assertSame('0011223344', $payout->account_number);
        $this->assertSame('••••3344', $payout->maskedAccountNumber());

        $rawPayoutRow = \Illuminate\Support\Facades\DB::table('affiliate_payouts')->where('id', $payout->id)->first();
        $this->assertSame('Access Bank', $rawPayoutRow->bank_name);
        $this->assertSame('044', $rawPayoutRow->bank_code);
        $this->assertNotEquals('Original Church Ministry', $rawPayoutRow->account_name);
        $this->assertStringStartsWith('eyJ', $rawPayoutRow->account_name);
        $this->assertNotEquals('0011223344', $rawPayoutRow->account_number);
        $this->assertStringStartsWith('eyJ', $rawPayoutRow->account_number);

        // Verify sensitive fields hidden from AffiliatePayout serialization
        $payoutArray = $payout->toArray();
        $this->assertArrayNotHasKey('account_number', $payoutArray);
        $this->assertArrayNotHasKey('account_name', $payoutArray);
        $this->assertStringNotContainsString('0011223344', $payout->toJson());
        $this->assertStringNotContainsString('Original Church Ministry', $payout->toJson());

        // 2. Affiliate later edits their bank destination to a completely different bank
        $this->affiliate->update([
            'bank_name' => 'Guaranty Trust Bank',
            'bank_code' => '058',
            'account_name' => 'Updated Personal Account',
            'account_number' => '9988776655',
        ]);

        // 3. Verify existing payout snapshot remains 100% immutable
        $payout->refresh();
        $this->assertSame('Access Bank', $payout->bank_name);
        $this->assertSame('044', $payout->bank_code);
        $this->assertSame('Original Church Ministry', $payout->account_name);
        $this->assertSame('0011223344', $payout->account_number);
        $this->assertSame('••••3344', $payout->maskedAccountNumber());
        $this->assertSame('Access Bank (••••3344) - Original Church Ministry', $payout->payoutDestinationSummary());

        // Verify admin payout show screen renders the immutable snapshot (masked, with bank code) and never full number or new bank
        $adminShow = $this->actingAs($this->admin)->get(route('admin.affiliate-payouts.show', $payout));
        $adminShow->assertOk();
        $adminShow->assertSee('Access Bank');
        $adminShow->assertSee('044');
        $adminShow->assertSee('Original Church Ministry');
        $adminShow->assertSee('••••3344');
        $adminShow->assertDontSee('0011223344');
        $adminShow->assertDontSee('9988776655');
        $adminShow->assertDontSee('Guaranty Trust Bank');
        $adminShow->assertDontSee('Updated Personal Account');

        // 4. Approve and mark payout paid, then verify email & audit safety
        $approved = $service->approvePayout($payout, $this->admin);
        $paid = $service->markPayoutPaid($approved, $this->admin, 'Manual bank wire completed');

        $approvedEmailHtml = (new AffiliatePayoutApprovedMail($approved))->render();
        $paidEmailHtml = (new AffiliatePayoutPaidMail($paid))->render();

        $this->assertStringNotContainsString('0011223344', $approvedEmailHtml);
        $this->assertStringNotContainsString('Original Church Ministry', $approvedEmailHtml);
        $this->assertStringNotContainsString('0011223344', $paidEmailHtml);
        $this->assertStringNotContainsString('Original Church Ministry', $paidEmailHtml);
        $this->assertStringContainsString('Access Bank (••••3344)', $paidEmailHtml);

        // 5. Verify no audit record contains raw account_number or raw account_name
        $audits = AffiliateCommissionAudit::query()->where('affiliate_account_id', $this->affiliate->id)->get();
        $this->assertNotEmpty($audits);
        foreach ($audits as $audit) {
            $blob = json_encode($audit->toArray());
            $this->assertStringNotContainsString('0011223344', $blob);
            $this->assertStringNotContainsString('9988776655', $blob);
            $this->assertStringNotContainsString('Original Church Ministry', $blob);
            $this->assertStringNotContainsString('Updated Personal Account', $blob);
        }
    }

    public function test_payout_allocation_invariants_and_transitions(): void
    {
        $service = app(AffiliateCommissionService::class);
        $this->affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('invariants-church', 'Invariants Church');

        // Create a HELD commission
        $heldComm = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_inv_held',
            'idempotency_key' => 'idemp_inv_held',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 10000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 1000000,
            'status' => AffiliateCommissionStatus::Held,
            'hold_until' => now()->addDays(10),
            'confirmed_at' => now(),
        ]);

        // Create a REVERSED commission
        $reversedComm = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_inv_rev',
            'idempotency_key' => 'idemp_inv_rev',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 10000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 1000000,
            'status' => AffiliateCommissionStatus::Reversed,
            'hold_until' => now()->subDays(5),
            'confirmed_at' => now()->subDays(20),
            'reversed_at' => now(),
            'reversal_reason' => 'Dispute',
        ]);

        // Create a PAYABLE commission
        $payableComm = AffiliateCommission::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $org->id,
            'payment_reference' => 'pay_inv_payable',
            'idempotency_key' => 'idemp_inv_payable',
            'attribution_source' => 'first_touch',
            'currency' => 'NGN',
            'gross_amount_minor' => 10000000,
            'commission_rate_bps' => 1000,
            'commission_amount_minor' => 1000000,
            'status' => AffiliateCommissionStatus::Payable,
            'hold_until' => now()->subDay(),
            'confirmed_at' => now()->subDays(15),
            'matured_at' => now()->subDay(),
        ]);

        // Invariant 1: Cannot allocate HELD commissions
        try {
            $service->createPayout($this->affiliate, 'NGN', [$heldComm->id], $this->admin);
            $this->fail('Expected ValidationException when allocating held commission.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('commissions', $e->errors());
        }

        // Invariant 2: Cannot allocate REVERSED commissions
        try {
            $service->createPayout($this->affiliate, 'NGN', [$reversedComm->id], $this->admin);
            $this->fail('Expected ValidationException when allocating reversed commission.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('commissions', $e->errors());
        }

        // Invariant 3: Allocating PAYABLE commission succeeds
        $payout = $service->createPayout($this->affiliate, 'NGN', [$payableComm->id], $this->admin);
        $this->assertSame(AffiliatePayoutStatus::Draft, $payout->status);
        $this->assertSame($payout->id, $payableComm->fresh()->affiliate_payout_id);

        // Invariant 4: No double-allocation
        try {
            $service->createPayout($this->affiliate, 'NGN', [$payableComm->id], $this->admin);
            $this->fail('Expected ValidationException when allocating already allocated commission.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('commissions', $e->errors());
        }

        // Invariant 5: Approving payout does NOT mark commissions as PAID
        $approvedPayout = $service->approvePayout($payout, $this->admin);
        $this->assertSame(AffiliatePayoutStatus::Approved, $approvedPayout->status);
        $this->assertSame(AffiliateCommissionStatus::Payable, $payableComm->fresh()->status);

        // Invariant 6: Only markPayoutPaid moves commissions to PAID
        $paidPayout = $service->markPayoutPaid($approvedPayout, $this->admin, 'Disbursed via transfer');
        $this->assertSame(AffiliatePayoutStatus::Paid, $paidPayout->status);
        $this->assertSame(AffiliateCommissionStatus::Paid, $payableComm->fresh()->status);
        $this->assertNotNull($paidPayout->paid_at);
    }

    public function test_affiliate_qualification_achieved_event_fires_exactly_once_idempotently(): void
    {
        Event::fake([AffiliateQualificationAchieved::class]);

        $service = app(AffiliateCommissionService::class);
        $affiliate = AffiliateAccount::query()->create([
            'user_id' => User::factory()->create()->id,
            'referral_code' => 'IDEMP_TEST',
            'status' => AffiliateStatus::Approved,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'payout_qualified_at' => null,
        ]);

        // Create 5 distinct paying organizations
        for ($i = 1; $i <= 5; $i++) {
            $org = $this->createOrganization("idemp-org-{$i}");
            AffiliateAttribution::query()->create([
                'affiliate_account_id' => $affiliate->id,
                'organization_id' => $org->id,
                'source' => 'first_touch',
                'attributed_at' => now(),
            ]);

            AffiliateCommission::query()->create([
                'affiliate_account_id' => $affiliate->id,
                'organization_id' => $org->id,
                'payment_reference' => "pay_idemp_{$i}",
                'idempotency_key' => "idemp_key_{$i}",
                'attribution_source' => 'first_touch',
                'currency' => 'NGN',
                'gross_amount_minor' => 10000000,
                'commission_rate_bps' => 1000,
                'commission_amount_minor' => 1000000,
                'status' => AffiliateCommissionStatus::Held,
                'confirmed_at' => now(),
                'hold_until' => now()->addDays(14),
            ]);
        }

        // First call triggers qualification
        $firstResult = $service->checkAndRecordQualification($affiliate);
        $this->assertTrue($firstResult);
        $this->assertNotNull($affiliate->fresh()->payout_qualified_at);
        Event::assertDispatched(AffiliateQualificationAchieved::class, 1);

        // Second call on already qualified affiliate must be idempotent and NOT dispatch duplicate event
        $secondResult = $service->checkAndRecordQualification($affiliate->fresh());
        $this->assertTrue($secondResult);
        Event::assertDispatched(AffiliateQualificationAchieved::class, 1); // Still exactly 1
    }

    public function test_risk_indicators_are_strictly_informational_without_mutations(): void
    {
        // 1. Add multiple blocked self referrals
        $selfOrg = $this->createOrganization('pastor-church-risk', 'Pastor Risk Church');
        AffiliateAttribution::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'organization_id' => $selfOrg->id,
            'source' => 'blocked_self_referral',
            'attributed_at' => now(),
            'notes' => 'Blocked self referral',
        ]);

        $initialStatus = $this->affiliate->status;
        $initialQualified = $this->affiliate->payout_qualified_at;

        // 2. Fetch risk flags
        $flags = $this->affiliate->riskFlags();
        $this->assertNotEmpty($flags);
        $this->assertSame('self_referral_attempt', $flags[0]['type']);

        // 3. Confirm zero mutations on affiliate model
        $fresh = $this->affiliate->fresh();
        $this->assertSame($initialStatus, $fresh->status);
        $this->assertSame($initialQualified, $fresh->payout_qualified_at);
        $this->assertNull($fresh->suspended_at);
    }

    public function test_payout_approved_email_neutral_copy_without_sla_timelines(): void
    {
        $payout = AffiliatePayout::query()->create([
            'affiliate_account_id' => $this->affiliate->id,
            'payout_reference' => 'PO_SLA_TEST',
            'currency' => 'NGN',
            'amount_minor' => 500000,
            'status' => AffiliatePayoutStatus::Approved,
            'admin_id' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $mailable = new AffiliatePayoutApprovedMail($payout);
        $rendered = $mailable->render();

        // Exact approved neutral copy
        $this->assertStringContainsString('Your payout has been approved and is scheduled for manual processing.', $rendered);

        // Zero invented timelines or SLA promises
        $this->assertStringNotContainsString('business days', strtolower($rendered));
        $this->assertStringNotContainsString('within 24', strtolower($rendered));
        $this->assertStringNotContainsString('3-5', strtolower($rendered));
        $this->assertStringNotContainsString('48 hours', strtolower($rendered));
    }
}

