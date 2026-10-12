<?php

namespace Tests\Feature;

use App\Enums\AffiliateAttributionSource;
use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Enums\AffiliateStatus;
use App\Enums\OrgRole;
use App\Events\SubscriptionPaymentConfirmed;
use App\Events\SubscriptionPaymentReversed;
use App\Models\AffiliateAccount;
use App\Models\AffiliateAttribution;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AffiliateCommissionService;
use App\Services\PaystackPaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class AffiliateCommissionEngineTest extends TestCase
{
    use RefreshDatabase;

    private AffiliateCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AffiliateCommissionService::class);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_eligible_first_payment_creates_held_commission(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-one');
        $this->attributeOrg($org, $affiliate);

        $plan = $this->createPlan(1000000); // 10,000 NGN in kobo
        $checkout = PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'reference' => 'ref_first_123',
            'status' => PaymentCheckout::STATUS_PENDING,
            'amount' => 1000000,
            'currency' => 'NGN',
        ]);

        $commission = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            [
                'reference' => 'ref_first_123',
                'amount' => 1000000,
                'currency' => 'NGN',
                'id' => 98765,
            ],
            $checkout,
        );

        $this->assertNotNull($commission);
        $this->assertSame(AffiliateCommissionStatus::Held, $commission->status);
        $this->assertSame($affiliate->id, $commission->affiliate_account_id);
        $this->assertSame($org->id, $commission->organization_id);
        $this->assertSame(1000000, $commission->gross_amount_minor);
        $this->assertSame(1000, $commission->commission_rate_bps);
        $this->assertSame(100000, $commission->commission_amount_minor); // 10% = 1,000 NGN
        $this->assertSame('NGN', $commission->currency);
        $this->assertFalse($commission->is_renewal);
        $this->assertSame('sub_payment:ref_first_123', $commission->idempotency_key);
        $this->assertTrue($commission->hold_until->isFuture());

        $this->assertDatabaseHas('affiliate_commission_audits', [
            'affiliate_commission_id' => $commission->id,
            'action' => 'commission_created',
            'to_status' => 'held',
        ]);
    }

    public function test_renewal_payment_creates_recurring_commission(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-recurring');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(2000000);

        // First payment
        $c1 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_init_1', 'amount' => 2000000, 'currency' => 'NGN'],
        );
        $this->assertNotNull($c1);

        // Renewal payment (with subscription_code metadata)
        $c2 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            [
                'reference' => 'ref_renew_2',
                'amount' => 2000000,
                'currency' => 'NGN',
                'subscription' => ['subscription_code' => 'SUB_999'],
            ],
        );

        $this->assertNotNull($c2);
        $this->assertNotSame($c1->id, $c2->id);
        $this->assertTrue($c2->is_renewal);
        $this->assertSame(200000, $c2->commission_amount_minor);
        $this->assertSame(AffiliateCommissionStatus::Held, $c2->status);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-dup');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1500000);

        $c1 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_same_ref', 'amount' => 1500000, 'currency' => 'NGN'],
        );

        // Exact same payment reference retried
        $c2 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_same_ref', 'amount' => 1500000, 'currency' => 'NGN'],
        );

        $this->assertSame($c1->id, $c2->id);
        $this->assertSame(1, AffiliateCommission::query()->where('payment_reference', 'ref_same_ref')->count());
    }

    public function test_failed_payment_or_free_plan_does_not_create_commission(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-free');
        $this->attributeOrg($org, $affiliate);

        $freePlan = Plan::query()->create([
            'name' => 'Free Plan',
            'slug' => 'free',
            'amount' => 0,
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $freePlan,
            ['reference' => 'ref_free_pay', 'amount' => 0, 'currency' => 'NGN'],
        );

        $this->assertNull($comm);
        $this->assertSame(0, AffiliateCommission::query()->count());
    }

    public function test_unattributed_or_organic_org_does_not_create_commission(): void
    {
        $org = $this->createOrganization('church-organic');
        $plan = $this->createPlan(1000000);

        // No attribution record
        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_organic', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $this->assertNull($comm);

        // Attributed with Organic source (not earning eligible)
        $affiliate = $this->createAffiliate();
        AffiliateAttribution::query()->create([
            'organization_id' => $org->id,
            'affiliate_account_id' => $affiliate->id,
            'source' => AffiliateAttributionSource::Organic,
            'attributed_at' => now(),
        ]);

        $comm2 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_organic_2', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $this->assertNull($comm2);
        $this->assertSame(0, AffiliateCommission::query()->count());
    }

    public function test_self_referral_and_controlled_org_strictly_blocked(): void
    {
        $user = User::factory()->create();
        $affiliate = $this->createAffiliate($user);

        $org = $this->createOrganization('church-self');
        // Affiliate's user is owner of the church
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $this->attributeOrg($org, $affiliate);

        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_self_pay', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        $this->assertNull($comm);
        $this->assertSame(0, AffiliateCommission::query()->count());
    }

    public function test_unapproved_affiliate_does_not_earn_commission(): void
    {
        $affiliate = $this->createAffiliate(status: AffiliateStatus::Pending);
        $org = $this->createOrganization('church-pending-aff');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_unappr', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        $this->assertNull($comm);
        $this->assertSame(0, AffiliateCommission::query()->count());
    }

    public function test_exact_ten_percent_integer_calculation_and_multi_currency(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-usd');
        $this->attributeOrg($org, $affiliate);

        // $49.00 USD (4900 cents)
        $usdPlan = $this->createPlan(4900, 'USD');

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $usdPlan,
            ['reference' => 'ref_usd_test', 'amount' => 4900, 'currency' => 'USD'],
        );

        $this->assertNotNull($comm);
        $this->assertSame('USD', $comm->currency);
        $this->assertSame(4900, $comm->gross_amount_minor);
        $this->assertSame(490, $comm->commission_amount_minor); // 490 cents = $4.90
    }

    public function test_five_distinct_paying_organisations_triggers_permanent_qualification(): void
    {
        $affiliate = $this->createAffiliate();
        $this->assertFalse($affiliate->isQualified());
        $this->assertNull($affiliate->payout_qualified_at);

        $plan = $this->createPlan(1000000);

        // 4 distinct paying churches
        for ($i = 1; $i <= 4; $i++) {
            $org = $this->createOrganization("church-qual-{$i}");
            $this->attributeOrg($org, $affiliate);
            $this->service->recordSubscriptionPayment(
                $org,
                $plan,
                ['reference' => "ref_qual_{$i}", 'amount' => 1000000, 'currency' => 'NGN'],
            );
            $this->assertFalse($affiliate->fresh()->isQualified());
            $this->assertSame($i, $affiliate->fresh()->qualifyingOrganizationsCount());
        }

        // 5th distinct paying church triggers permanent qualification
        $org5 = $this->createOrganization('church-qual-5');
        $this->attributeOrg($org5, $affiliate);
        $this->service->recordSubscriptionPayment(
            $org5,
            $plan,
            ['reference' => 'ref_qual_5', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        $this->assertTrue($affiliate->fresh()->isQualified());
        $this->assertNotNull($affiliate->fresh()->payout_qualified_at);
        $this->assertSame(5, $affiliate->fresh()->qualifyingOrganizationsCount());
    }

    public function test_renewals_do_not_double_count_towards_qualification(): void
    {
        $affiliate = $this->createAffiliate();
        $org1 = $this->createOrganization('church-single');
        $this->attributeOrg($org1, $affiliate);
        $plan = $this->createPlan(1000000);

        // 3 consecutive payments from the exact same church
        $this->service->recordSubscriptionPayment($org1, $plan, ['reference' => 'ref_s_1', 'amount' => 1000000, 'currency' => 'NGN']);
        $this->service->recordSubscriptionPayment($org1, $plan, ['reference' => 'ref_s_2', 'amount' => 1000000, 'currency' => 'NGN']);
        $this->service->recordSubscriptionPayment($org1, $plan, ['reference' => 'ref_s_3', 'amount' => 1000000, 'currency' => 'NGN']);

        $this->assertSame(1, $affiliate->fresh()->qualifyingOrganizationsCount());
        $this->assertFalse($affiliate->fresh()->isQualified());
    }

    public function test_permanent_qualification_remains_even_if_organizations_churn(): void
    {
        $affiliate = $this->createAffiliate();
        $plan = $this->createPlan(1000000);

        for ($i = 1; $i <= 5; $i++) {
            $org = $this->createOrganization("church-churn-{$i}");
            $this->attributeOrg($org, $affiliate);
            $this->service->recordSubscriptionPayment(
                $org,
                $plan,
                ['reference' => "ref_churn_{$i}", 'amount' => 1000000, 'currency' => 'NGN'],
            );
        }

        $this->assertTrue($affiliate->fresh()->isQualified());
        $originalQualifiedAt = $affiliate->fresh()->payout_qualified_at;

        // One organization churns (subscription cancelled)
        $org1 = Organization::query()->where('slug', 'church-churn-1')->firstOrFail();
        if ($org1->subscription) {
            $org1->subscription->update(['status' => \App\Enums\SubscriptionStatus::Cancelled]);
        }

        $this->assertTrue($affiliate->fresh()->isQualified());
        $this->assertSame($originalQualifiedAt->timestamp, $affiliate->fresh()->payout_qualified_at->timestamp);
    }

    public function test_fourteen_day_hold_advancement_for_qualified_affiliates(): void
    {
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]); // Already qualified

        $org = $this->createOrganization('church-hold-test');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_hold_1', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        $this->assertSame(AffiliateCommissionStatus::Held, $comm->status);

        // Day 10: Hold is not yet mature
        $this->travel(10)->days();
        $this->service->processMatureHolds();
        $this->assertSame(AffiliateCommissionStatus::Held, $comm->fresh()->status);

        // Day 15: Hold has matured (> 14 days)
        $this->travel(5)->days();
        $transitioned = $this->service->processMatureHolds();

        $this->assertSame(1, $transitioned);
        $this->assertSame(AffiliateCommissionStatus::Payable, $comm->fresh()->status);
        $this->assertNotNull($comm->fresh()->payable_at);
    }

    public function test_pre_qualification_holds_remain_held_until_qualification_achieved(): void
    {
        $affiliate = $this->createAffiliate();
        // Not qualified!
        $this->assertFalse($affiliate->isQualified());

        $org = $this->createOrganization('church-pre-qual');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_pre_1', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        // 20 days pass
        $this->travel(20)->days();
        $this->service->processMatureHolds();

        // Stays HELD because affiliate is not yet qualified
        $this->assertSame(AffiliateCommissionStatus::Held, $comm->fresh()->status);

        // Now qualify the affiliate
        for ($i = 2; $i <= 5; $i++) {
            $otherOrg = $this->createOrganization("church-pre-{$i}");
            $this->attributeOrg($otherOrg, $affiliate);
            $this->service->recordSubscriptionPayment(
                $otherOrg,
                $plan,
                ['reference' => "ref_pre_{$i}", 'amount' => 1000000, 'currency' => 'NGN'],
            );
        }

        $this->assertTrue($affiliate->fresh()->isQualified());
        // The first commission had already passed 14 days, so checkAndRecordQualification releases it immediately!
        $this->assertSame(AffiliateCommissionStatus::Payable, $comm->fresh()->status);
    }

    public function test_reversal_voids_held_and_reverses_payable(): void
    {
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-rev-test');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        // 1. Reversing a HELD commission
        $c1 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_rev_held', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $this->assertSame(AffiliateCommissionStatus::Held, $c1->status);

        $this->service->reverseCommission($c1, 'Chargeback requested');
        $this->assertSame(AffiliateCommissionStatus::Voided, $c1->fresh()->status);
        $this->assertSame('Chargeback requested', $c1->fresh()->reversal_reason);

        // 2. Reversing a PAYABLE commission
        $c2 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_rev_pay', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();
        $this->assertSame(AffiliateCommissionStatus::Payable, $c2->fresh()->status);

        $this->service->reverseCommission($c2, 'Customer refunded');
        $this->assertSame(AffiliateCommissionStatus::Reversed, $c2->fresh()->status);
        $this->assertSame('Customer refunded', $c2->fresh()->reversal_reason);
    }

    public function test_reversal_on_paid_commission_creates_negative_adjustment_ledger_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-paid-rev');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_paid_rev', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        // Create payout draft, approve it, and mark paid
        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);
        $this->service->approvePayout($payout, $admin);
        $this->service->markPayoutPaid($payout, $admin);

        $this->assertSame(AffiliateCommissionStatus::Paid, $comm->fresh()->status);

        // Now payment is disputed/reversed
        $adjustment = $this->service->reverseCommission($comm, 'Fraud dispute post settlement');

        $this->assertNotSame($comm->id, $adjustment->id);
        $this->assertSame(AffiliateCommissionStatus::Paid, $comm->fresh()->status); // Historical row untouched!
        $this->assertSame(AffiliateCommissionStatus::Reversed, $adjustment->status);
        $this->assertSame(-100000, $adjustment->commission_amount_minor);
        $this->assertSame(-1000000, $adjustment->gross_amount_minor);
        $this->assertSame($comm->id, $adjustment->reversal_of_commission_id);
    }

    public function test_payout_batch_workflow_and_transaction_safety(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-payout-flow');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $c1 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_p_1', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $c2 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_p_2', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        $this->travel(15)->days();
        $this->service->processMatureHolds();

        // Create payout (starts in DRAFT)
        $payout = $this->service->createPayout($affiliate, 'NGN', [$c1->id, $c2->id], $admin, 'Test memo');
        $this->assertSame(200000, $payout->amount_minor);
        $this->assertSame(AffiliatePayoutStatus::Draft, $payout->status);
        $this->assertSame($payout->id, $c1->fresh()->affiliate_payout_id);

        // Attempting to include already bundled commission fails (no double inclusion)
        $this->expectException(ValidationException::class);
        $this->service->createPayout($affiliate, 'NGN', [$c1->id], $admin);
    }

    public function test_cancelling_payout_releases_commissions_back_to_payable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-payout-cancel');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $c1 = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_cancel_1', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$c1->id], $admin);
        $this->assertSame($payout->id, $c1->fresh()->affiliate_payout_id);

        // Cancel payout
        $this->service->cancelPayout($payout, $admin, 'Incorrect bank details');
        $this->assertSame(AffiliatePayoutStatus::Cancelled, $payout->fresh()->status);
        $this->assertNull($c1->fresh()->affiliate_payout_id);
        $this->assertSame(AffiliateCommissionStatus::Payable, $c1->fresh()->status);
    }

    public function test_affiliate_cannot_inspect_another_affiliates_commissions(): void
    {
        $affiliate1 = $this->createAffiliate();
        $affiliate2 = $this->createAffiliate();

        $org = $this->createOrganization('church-sec');
        $this->attributeOrg($org, $affiliate1);
        $plan = $this->createPlan(1000000);

        $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_sec_test', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        // Affiliate 2 views statement
        $response = $this->actingAs($affiliate2->user)
            ->get(route('affiliate.commissions.index'));

        $response->assertOk();
        $response->assertDontSee('ref_sec_test');
        $response->assertSee('No commission transactions recorded yet');
    }

    public function test_admin_authorization_required_for_commission_and_payout_routes(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);
        $org = $this->createOrganization('church-regular');
        $org->users()->attach($regularUser->id, ['role' => OrgRole::Owner->value]);

        $this->actingAs($regularUser)
            ->get(route('admin.affiliate-commissions.index'))
            ->assertForbidden();

        $this->actingAs($regularUser)
            ->get(route('admin.affiliate-payouts.index'))
            ->assertForbidden();
    }

    public function test_paystack_webhook_charge_success_triggers_commission_creation(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-webhook');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);
        $plan->update(['paystack_plan_code' => 'PLN_test_123']);

        $checkout = PaymentCheckout::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'reference' => 'ref_webhook_hook',
            'status' => PaymentCheckout::STATUS_PENDING,
            'amount' => 1000000,
            'currency' => 'NGN',
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => 'ref_webhook_hook',
                'amount' => 1000000,
                'currency' => 'NGN',
                'customer' => ['customer_code' => 'CUS_test_hook'],
                'metadata' => [
                    'organization_id' => $org->id,
                    'plan_id' => $plan->id,
                ],
            ],
        ];

        SiteSetting::putPaystackSecret('secret_test_key');
        $rawPayload = json_encode($payload);
        $signature = hash_hmac('sha512', $rawPayload, 'secret_test_key');

        $this->call(
            'POST',
            route('webhooks.paystack'),
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $rawPayload,
        )->assertOk();

        $this->assertSame(PaymentCheckout::STATUS_SUCCEEDED, $checkout->fresh()->status);
        $this->assertDatabaseHas('affiliate_commissions', [
            'payment_reference' => 'ref_webhook_hook',
            'affiliate_account_id' => $affiliate->id,
            'status' => AffiliateCommissionStatus::Held->value,
            'commission_amount_minor' => 100000,
        ]);
    }

    public function test_paystack_webhook_refund_processed_triggers_commission_reversal(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-refund-webhook');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_refund_target', 'amount' => 1000000, 'currency' => 'NGN'],
        );

        $payload = [
            'event' => 'refund.processed',
            'data' => [
                'transaction_reference' => 'ref_refund_target',
                'reason' => 'Customer requested chargeback',
            ],
        ];

        SiteSetting::putPaystackSecret('secret_test_key');
        $rawPayload = json_encode($payload);
        $signature = hash_hmac('sha512', $rawPayload, 'secret_test_key');

        $this->call(
            'POST',
            route('webhooks.paystack'),
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $rawPayload,
        )->assertOk();

        $this->assertSame(AffiliateCommissionStatus::Voided, $comm->fresh()->status);
        $this->assertSame('Customer requested chargeback', $comm->fresh()->reversal_reason);
    }

    public function test_admin_can_manage_payouts_via_http(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $org = $this->createOrganization('church-admin-org');
        $org->users()->attach($admin->id, ['role' => OrgRole::Owner->value]);

        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_admin_flow', 'amount' => 1000000, 'currency' => 'NGN'],
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        // 1. Create payout draft
        $response = $this->actingAs($admin)->post(route('admin.affiliate-payouts.store'), [
            'affiliate_account_id' => $affiliate->id,
            'currency' => 'NGN',
            'commission_ids' => [$comm->id],
            'notes' => 'Batch payout created by admin',
        ]);

        $payout = AffiliatePayout::query()->firstOrFail();
        $response->assertRedirect(route('admin.affiliate-payouts.show', $payout));
        $this->assertSame(AffiliatePayoutStatus::Draft, $payout->status);

        // 2. Approve payout
        $this->actingAs($admin)->post(route('admin.affiliate-payouts.approve', $payout))
            ->assertRedirect();
        $this->assertSame(AffiliatePayoutStatus::Approved, $payout->fresh()->status);
        $this->assertSame($admin->id, $payout->fresh()->approved_by);

        // 3. Mark Paid
        $this->actingAs($admin)->post(route('admin.affiliate-payouts.mark-paid', $payout))
            ->assertRedirect();
        $this->assertSame(AffiliatePayoutStatus::Paid, $payout->fresh()->status);
        $this->assertSame(AffiliateCommissionStatus::Paid, $comm->fresh()->status);
    }

    public function test_affiliate_statement_renders_statement_data(): void
    {
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('grace-assembly');
        $org->update(['name' => 'Grace Assembly']);
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(2000000);

        $this->service->recordSubscriptionPayment(
            $org,
            $plan,
            ['reference' => 'ref_statement_test', 'amount' => 2000000, 'currency' => 'NGN'],
        );

        $response = $this->actingAs($affiliate->user)
            ->get(route('affiliate.commissions.index'));

        $response->assertOk();
        $response->assertSee('My Earnings &amp; Statement', false);
        $response->assertSee('Grace Assembly');
        $response->assertSee('NGN 20,000.00'); // gross
        $response->assertSee('NGN 2,000.00');  // 10% commission
        $response->assertSee('Qualified for Payouts');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Section 10 Predeploy Correction Pass Tests
    // ──────────────────────────────────────────────────────────────────────────

    public function test_provider_specific_confirmation_emits_canonical_domain_event(): void
    {
        Event::fake([SubscriptionPaymentConfirmed::class]);

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $org = $this->createOrganization('church-event-emit');
        $plan = $this->createPlan(1000000);

        $payload = [
            'status' => 'success',
            'reference' => 'ref_event_emit_1',
            'amount' => 1000000,
            'currency' => 'NGN',
            'customer' => ['customer_code' => 'CUS_emitter'],
            'metadata' => [
                'organization_id' => $org->id,
                'plan_id' => $plan->id,
            ],
        ];

        $confirmation->confirmFromPaystackData($payload);

        Event::assertDispatched(SubscriptionPaymentConfirmed::class, function (SubscriptionPaymentConfirmed $event) use ($org, $plan): bool {
            return $event->organizationId === $org->id
                && $event->planId === $plan->id
                && $event->paymentReference === 'ref_event_emit_1'
                && $event->amountMinor === 1000000
                && $event->currency === 'NGN';
        });
    }

    public function test_commission_generated_from_canonical_payment_event(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-canon-ev');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(3000000);

        event(new SubscriptionPaymentConfirmed(
            organizationId: $org->id,
            planId: $plan->id,
            paymentReference: 'ref_canon_evt_test',
            amountMinor: 3000000,
            currency: 'NGN',
            confirmedAt: now(),
            isRenewal: false,
        ));

        $this->assertDatabaseHas('affiliate_commissions', [
            'payment_reference' => 'ref_canon_evt_test',
            'affiliate_account_id' => $affiliate->id,
            'organization_id' => $org->id,
            'status' => AffiliateCommissionStatus::Held->value,
            'gross_amount_minor' => 3000000,
            'commission_amount_minor' => 300000,
        ]);
    }

    public function test_duplicate_confirmed_event_remains_idempotent(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-canon-dup');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $event = new SubscriptionPaymentConfirmed(
            organizationId: $org->id,
            planId: $plan->id,
            paymentReference: 'ref_canon_dup_1',
            amountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
            isRenewal: false,
        );

        event($event);
        event($event); // Duplicate dispatch

        $this->assertSame(1, AffiliateCommission::query()->where('payment_reference', 'ref_canon_dup_1')->count());
    }

    public function test_duplicate_reversal_remains_idempotent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-dup-rev');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_dup_rev_p',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );

        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);
        $this->service->approvePayout($payout, $admin);
        $this->service->markPayoutPaid($payout, $admin);

        // First reversal event
        event(new SubscriptionPaymentReversed(
            paymentReference: 'ref_dup_rev_p',
            reversalReference: 'ref_dup_rev_p',
            reason: 'Chargeback received',
        ));

        // Duplicate reversal event
        event(new SubscriptionPaymentReversed(
            paymentReference: 'ref_dup_rev_p',
            reversalReference: 'ref_dup_rev_p',
            reason: 'Duplicate dispute webhook',
        ));

        // Assert exactly one negative adjustment row created
        $adjustments = AffiliateCommission::query()->where('reversal_of_commission_id', $comm->id)->get();
        $this->assertCount(1, $adjustments);
        $this->assertSame(-100000, $adjustments->first()->commission_amount_minor);
    }

    public function test_draft_payout_creation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-payout-draft');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_draft_pay',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin, 'Draft batch notes');

        $this->assertSame(AffiliatePayoutStatus::Draft, $payout->status);
        $this->assertSame($admin->id, $payout->admin_id);
        $this->assertSame($admin->id, $payout->createdBy->id);
        $this->assertNull($payout->approved_by);
        $this->assertNull($payout->approved_at);
        $this->assertNull($payout->paid_by);
        $this->assertNull($payout->paid_at);
        $this->assertTrue($payout->canBeApproved());
        $this->assertFalse($payout->canBePaid());
    }

    public function test_draft_cannot_be_marked_paid(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-draft-pay-block');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_draft_guard',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Draft payouts must be approved before they can be marked paid.');
        $this->service->markPayoutPaid($payout, $admin);
    }

    public function test_approve_payout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-approve-payout');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_approve_pay',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);
        $approved = $this->service->approvePayout($payout, $admin);

        $this->assertSame(AffiliatePayoutStatus::Approved, $approved->status);
        $this->assertSame($admin->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertTrue($approved->canBePaid());
        $this->assertFalse($approved->canBeApproved());
    }

    public function test_approved_payout_can_be_marked_paid(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-approved-to-paid');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_appr_to_paid',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);
        $this->service->approvePayout($payout, $admin);

        $paid = $this->service->markPayoutPaid($payout, $admin, 'Settled via wire transfer');

        $this->assertSame(AffiliatePayoutStatus::Paid, $paid->status);
        $this->assertSame($admin->id, $paid->paid_by);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame(AffiliateCommissionStatus::Paid, $comm->fresh()->status);
    }

    public function test_paid_payout_cannot_be_cancelled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-paid-cancel-block');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_paid_no_cancel',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);
        $this->service->approvePayout($payout, $admin);
        $this->service->markPayoutPaid($payout, $admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Paid payouts cannot be cancelled.');
        $this->service->cancelPayout($payout, $admin, 'Attempt to cancel settled batch');
    }

    public function test_cancelled_payout_cannot_be_paid(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-cancel-no-pay');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_cancel_no_pay',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);
        $this->service->cancelPayout($payout, $admin, 'Cancelled due to issue');

        $this->expectException(ValidationException::class);
        $this->service->markPayoutPaid($payout, $admin);
    }

    public function test_invalid_state_transitions_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $affiliate = $this->createAffiliate();
        $affiliate->update(['payout_qualified_at' => now()]);
        $org = $this->createOrganization('church-invalid-transitions');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_invalid_trans',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );
        $this->travel(15)->days();
        $this->service->processMatureHolds();

        $payout = $this->service->createPayout($affiliate, 'NGN', [$comm->id], $admin);
        $this->service->cancelPayout($payout, $admin, 'Cancelled');

        // Cancelled payout cannot be approved
        $this->expectException(ValidationException::class);
        $this->service->approvePayout($payout, $admin);
    }

    public function test_pre_qualification_commission_accrues_held(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-accrue-held');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_accrue_held_test',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );

        $this->assertSame(AffiliateCommissionStatus::Held, $comm->status);
        $this->assertFalse($affiliate->isQualified());

        // Even after 30 days, stays held because affiliate is not qualified
        $this->travel(30)->days();
        $this->service->processMatureHolds();
        $this->assertSame(AffiliateCommissionStatus::Held, $comm->fresh()->status);
    }

    public function test_fifth_valid_paying_organisation_unlocks_qualification(): void
    {
        $affiliate = $this->createAffiliate();
        $plan = $this->createPlan(1000000);

        for ($i = 1; $i <= 4; $i++) {
            $org = $this->createOrganization("church-unlock-{$i}");
            $this->attributeOrg($org, $affiliate);
            $this->service->recordConfirmedSubscriptionPayment(
                organization: $org,
                plan: $plan,
                paymentReference: "ref_unlock_{$i}",
                grossAmountMinor: 1000000,
                currency: 'NGN',
                confirmedAt: now(),
            );
        }

        $this->assertFalse($affiliate->fresh()->isQualified());

        // 5th paying church unlocks qualification
        $org5 = $this->createOrganization('church-unlock-5');
        $this->attributeOrg($org5, $affiliate);
        $this->service->recordConfirmedSubscriptionPayment(
            organization: $org5,
            plan: $plan,
            paymentReference: 'ref_unlock_5',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );

        $this->assertTrue($affiliate->fresh()->isQualified());
        $this->assertNotNull($affiliate->fresh()->payout_qualified_at);
    }

    public function test_reversed_pre_qualification_org_stops_counting_if_no_other_valid_eligible_payment(): void
    {
        $affiliate = $this->createAffiliate();
        $plan = $this->createPlan(1000000);

        $org = $this->createOrganization('church-rev-count');
        $this->attributeOrg($org, $affiliate);

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_rev_count_1',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );

        $this->assertSame(1, $affiliate->fresh()->qualifyingOrganizationsCount());

        // Reversal occurs before qualification
        event(new SubscriptionPaymentReversed(
            paymentReference: 'ref_rev_count_1',
            reason: 'Full refund before qualification',
        ));

        $this->assertSame(AffiliateCommissionStatus::Voided, $comm->fresh()->status);
        // The organisation no longer has any valid unreversed payment, so it stops counting!
        $this->assertSame(0, $affiliate->fresh()->qualifyingOrganizationsCount());
    }

    public function test_post_qualification_reversal_does_not_revoke_qualification(): void
    {
        $affiliate = $this->createAffiliate();
        $plan = $this->createPlan(1000000);

        for ($i = 1; $i <= 5; $i++) {
            $org = $this->createOrganization("church-post-q-{$i}");
            $this->attributeOrg($org, $affiliate);
            $this->service->recordConfirmedSubscriptionPayment(
                organization: $org,
                plan: $plan,
                paymentReference: "ref_post_q_{$i}",
                grossAmountMinor: 1000000,
                currency: 'NGN',
                confirmedAt: now(),
            );
        }

        $this->assertTrue($affiliate->fresh()->isQualified());
        $qualifiedAt = $affiliate->fresh()->payout_qualified_at;

        // One of the payments is reversed after qualification
        event(new SubscriptionPaymentReversed(
            paymentReference: 'ref_post_q_1',
            reason: 'Refunded after qualification achieved',
        ));

        // Qualification is permanent and remains intact!
        $this->assertTrue($affiliate->fresh()->isQualified());
        $this->assertSame($qualifiedAt->timestamp, $affiliate->fresh()->payout_qualified_at->timestamp);
    }

    public function test_partial_refund_behavior_matches_current_payment_domain_capability(): void
    {
        $affiliate = $this->createAffiliate();
        $org = $this->createOrganization('church-part-ref');
        $this->attributeOrg($org, $affiliate);
        $plan = $this->createPlan(1000000); // 10,000 NGN

        $comm = $this->service->recordConfirmedSubscriptionPayment(
            organization: $org,
            plan: $plan,
            paymentReference: 'ref_part_test',
            grossAmountMinor: 1000000,
            currency: 'NGN',
            confirmedAt: now(),
        );

        // Attempting a partial refund of 5,000 NGN (500000 minor)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Partial subscription refunds are not supported by the payment domain; ambiguous partial reversal for reference ref_part_test flagged for manual review.');

        $this->service->handlePaymentReversal(
            paymentReference: 'ref_part_test',
            amountMinor: 500000,
            reason: 'Partial refund attempt',
        );
    }

    private function createAffiliate(?User $user = null, AffiliateStatus $status = AffiliateStatus::Approved): AffiliateAccount
    {
        $user ??= User::factory()->create();

        return AffiliateAccount::query()->create([
            'user_id' => $user->id,
            'referral_code' => AffiliateAccount::generateReferralCode(),
            'status' => $status,
            'country_code' => 'NG',
            'state_region' => 'Lagos',
            'city' => 'Ikeja',
            'approved_at' => $status === AffiliateStatus::Approved ? now() : null,
        ]);
    }

    private function createOrganization(string $slug): Organization
    {
        return Organization::query()->create([
            'name' => 'Church '.ucfirst($slug),
            'slug' => $slug,
            'is_public' => true,
        ]);
    }

    private function attributeOrg(Organization $org, AffiliateAccount $affiliate): AffiliateAttribution
    {
        return AffiliateAttribution::query()->create([
            'organization_id' => $org->id,
            'affiliate_account_id' => $affiliate->id,
            'source' => AffiliateAttributionSource::FirstTouch,
            'attributed_at' => now(),
        ]);
    }

    private function createPlan(int $amount, string $currency = 'NGN'): Plan
    {
        return Plan::query()->create([
            'name' => 'Pro Plan',
            'slug' => 'pro-'.uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'is_active' => true,
            'interval' => 'monthly',
        ]);
    }
}
