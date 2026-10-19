<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Events\InvoiceIssued;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use App\Services\PaystackPaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceEnginePhase2Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Plan $starterPlan;
    private Plan $proPlan;
    private Plan $freePlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paystack.secret_key' => 'sk_test_secret']);

        $this->user = User::factory()->create(['is_admin' => false]);
        $this->org = Organization::query()->create([
            'name' => 'Grace Chapel',
            'slug' => 'grace-chapel-'.uniqid(),
        ]);
        $this->org->users()->attach($this->user->id, ['role' => OrgRole::Owner->value]);

        $this->freePlan = Plan::query()->create([
            'name' => 'Free',
            'slug' => 'free',
            'amount' => 0,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['live_streaming' => false],
            'is_active' => true,
            'is_custom' => false,
            'sort_order' => 0,
        ]);

        $this->starterPlan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-'.uniqid(),
            'paystack_plan_code' => 'PLN_starter_test',
            'amount' => 500000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['live_streaming' => true],
            'is_active' => true,
            'is_custom' => false,
            'sort_order' => 1,
        ]);

        $this->proPlan = Plan::query()->create([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'paystack_plan_code' => 'PLN_pro_test',
            'amount' => 1200000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['live_streaming' => true],
            'is_active' => true,
            'is_custom' => false,
            'sort_order' => 2,
        ]);
    }

    /** 1. Paid checkout creates invoice before payment */
    /** 2. Invoice is open, not paid */
    /** 3. Correct billing period */
    /** 4. Correct plan line item */
    /** 5. Correct total in minor units */
    /** 6. PaymentCheckout references invoice */
    public function test_paid_checkout_creates_open_authoritative_invoice_before_payment(): void
    {
        $this->fakePaystack();

        $response = $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan));

        $response->assertRedirect('https://checkout.paystack.com/test');

        // Invoice exists before payment
        $this->assertSame(1, Invoice::query()->count());
        $invoice = Invoice::query()->first();

        // Invoice is open, not paid
        $this->assertSame(InvoiceStatus::Open->value, $invoice->status);
        $this->assertTrue($invoice->isOpen());
        $this->assertFalse($invoice->isPaid());
        $this->assertNotNull($invoice->issued_at);
        $this->assertNotNull($invoice->due_at);
        $this->assertNull($invoice->paid_at);

        // Immediate checkout due date strategy: due_at equals issued_at
        $this->assertSame($invoice->issued_at->toDateTimeString(), $invoice->due_at->toDateTimeString());

        // Billing period: 2026-10-19 -> 2026-11-18 for monthly
        $expectedStart = now()->startOfDay();
        $expectedRenewsAt = $this->starterPlan->periodEndFrom($expectedStart);
        $expectedEnd = Carbon::parse($expectedRenewsAt)->subDay()->startOfDay();

        $this->assertSame($expectedStart->toDateString(), $invoice->billing_period_start->toDateString());
        $this->assertSame($expectedEnd->toDateString(), $invoice->billing_period_end->toDateString());

        // Totals in integer minor units
        $this->assertSame(500000, $invoice->subtotal);
        $this->assertSame(0, $invoice->discount_total);
        $this->assertSame(0, $invoice->tax_total);
        $this->assertSame(500000, $invoice->total);
        $this->assertSame(500000, $invoice->amount_due);
        $this->assertSame(0, $invoice->amount_paid);

        // Line item
        $this->assertSame(1, $invoice->lines()->count());
        $line = $invoice->lines()->first();
        $this->assertSame('Starter subscription', $line->description);
        $this->assertSame(1, $line->quantity);
        $this->assertSame(500000, $line->unit_amount);
        $this->assertSame(500000, $line->subtotal);
        $this->assertSame(500000, $line->total);

        // PaymentCheckout references invoice
        $checkout = PaymentCheckout::query()->where('organization_id', $this->org->id)->first();
        $this->assertNotNull($checkout);
        $this->assertSame($invoice->id, $checkout->invoice_id);
        $this->assertSame($invoice->id, $checkout->invoice->id);
        $this->assertSame(1, $invoice->paymentCheckouts()->count());
    }

    /** 7. Repeated checkout reuses same invoice */
    /** 8. Retry checkout can create new PaymentCheckout for same invoice */
    public function test_repeated_checkout_reuses_same_invoice_with_multiple_payment_checkouts(): void
    {
        $this->fakePaystack();

        // First checkout click
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan))
            ->assertRedirect('https://checkout.paystack.com/test');

        $this->assertSame(1, Invoice::query()->count());
        $invoice1 = Invoice::query()->first();
        $checkout1 = PaymentCheckout::query()->first();

        // Second checkout click (e.g. retry after cancel or page refresh)
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan))
            ->assertRedirect('https://checkout.paystack.com/test');

        // Still only 1 invoice
        $this->assertSame(1, Invoice::query()->count());
        $invoice2 = Invoice::query()->first();
        $this->assertSame($invoice1->id, $invoice2->id);

        // But 2 PaymentCheckouts referencing the same invoice
        $this->assertSame(2, PaymentCheckout::query()->count());
        $checkouts = PaymentCheckout::query()->get();
        $this->assertNotSame($checkouts[0]->reference, $checkouts[1]->reference);
        $this->assertSame($invoice1->id, $checkouts[0]->invoice_id);
        $this->assertSame($invoice1->id, $checkouts[1]->invoice_id);
        $this->assertSame(2, $invoice1->paymentCheckouts()->count());
    }

    /** 9. Free / trial users not invoiced until paid checkout */
    public function test_free_and_trial_users_not_invoiced_until_paid_checkout(): void
    {
        // Select free plan
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->freePlan));

        // Free plan activation must not generate invoices
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, PaymentCheckout::query()->count());

        // Subscription is on free period
        $sub = $this->org->fresh()->subscription;
        $this->assertNotNull($sub);
        $this->assertTrue($sub->isOnFreePeriod());

        // Scheduler scanning renewals must skip free / trial subscriptions
        $generator = app(InvoiceGenerationService::class);
        $renewals = $generator->generateUpcomingRenewals();

        $this->assertCount(0, $renewals);
        $this->assertSame(0, Invoice::query()->count());
    }

    /** 10. Cancelled subscription does not get renewal invoice */
    public function test_cancelled_subscription_does_not_get_renewal_invoice(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Cancelled,
            'renews_at' => now()->addDays(3),
        ]);

        $generator = app(InvoiceGenerationService::class);
        $invoice = $generator->generateRenewal($sub);

        $this->assertNull($invoice);
        $this->assertSame(0, Invoice::query()->count());
    }

    /** 11. Renewal invoice generated 7 days before renews_at */
    public function test_renewal_invoice_generated_7_days_before_renews_at_with_due_date_at_renewal(): void
    {
        $renewsAt = now()->addDays(5)->startOfDay();

        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Active,
            'renews_at' => $renewsAt,
        ]);

        $generator = app(InvoiceGenerationService::class);

        // Subscriptions renewing in 10 days should be skipped (too early)
        $subFar = Subscription::query()->create([
            'organization_id' => Organization::query()->create(['name' => 'Far', 'slug' => 'far-'.uniqid()])->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Active,
            'renews_at' => now()->addDays(12),
        ]);
        $this->assertNull($generator->generateRenewal($subFar));

        // Subscriptions renewing within 7 days get an invoice
        $invoice = $generator->generateRenewal($sub);

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Open->value, $invoice->status);
        $this->assertSame($this->starterPlan->id, $invoice->plan_id);

        // Recurring due date strategy: due_at = renews_at
        $this->assertSame($renewsAt->toDateTimeString(), $invoice->due_at->toDateTimeString());

        // Billing period starts at renews_at
        $this->assertSame($renewsAt->toDateString(), $invoice->billing_period_start->toDateString());
        $expectedEnd = Carbon::parse($this->starterPlan->periodEndFrom($renewsAt))->subDay()->startOfDay();
        $this->assertSame($expectedEnd->toDateString(), $invoice->billing_period_end->toDateString());

        // Line item and total
        $this->assertSame(500000, $invoice->total);
        $this->assertSame('Starter subscription', $invoice->lines->first()?->description);
    }

    /** 12. Renewal generation idempotent */
    public function test_renewal_generation_is_idempotent(): void
    {
        $renewsAt = now()->addDays(4)->startOfDay();

        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Active,
            'renews_at' => $renewsAt,
        ]);

        $generator = app(InvoiceGenerationService::class);

        $inv1 = $generator->generateRenewal($sub);
        $inv2 = $generator->generateRenewal($sub);

        $this->assertSame($inv1->id, $inv2->id);
        $this->assertSame(1, Invoice::query()->count());

        // Command run is also idempotent
        $this->artisan('billing:generate-renewal-invoices')->assertSuccessful();
        $this->assertSame(1, Invoice::query()->count());
    }

    /** 13. Plan switch voids obsolete open invoice */
    /** 14. Plan switch creates new correct invoice */
    public function test_plan_switch_voids_obsolete_open_invoice_and_creates_new_invoice(): void
    {
        $this->fakePaystack();

        // Creator checks out Starter plan
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan));

        $this->assertSame(1, Invoice::query()->count());
        $starterInvoice = Invoice::query()->first();
        $this->assertSame($this->starterPlan->id, $starterInvoice->plan_id);
        $this->assertSame(500000, $starterInvoice->total);
        $this->assertSame('open', $starterInvoice->status);

        // Creator changes mind and checks out Pro plan before paying Starter
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->proPlan));

        // Two invoices exist in history: old one is voided, new one is open
        $this->assertSame(2, Invoice::query()->count());

        $starterInvoice->refresh();
        $this->assertSame('void', $starterInvoice->status);
        $this->assertNotNull($starterInvoice->voided_at);
        $this->assertSame(500000, $starterInvoice->total); // amount was NOT mutated
        $this->assertSame($this->starterPlan->id, $starterInvoice->plan_id); // plan was NOT mutated
        $this->assertSame($this->proPlan->id, $starterInvoice->metadata['superseded_by_plan_id'] ?? null);

        $proInvoice = Invoice::query()->where('plan_id', $this->proPlan->id)->first();
        $this->assertNotNull($proInvoice);
        $this->assertSame('open', $proInvoice->status);
        $this->assertSame(1200000, $proInvoice->total);
        $this->assertSame('Pro subscription', $proInvoice->lines->first()?->description);

        // New PaymentCheckout points to the Pro invoice
        $latestCheckout = PaymentCheckout::query()->latest('id')->first();
        $this->assertSame($proInvoice->id, $latestCheckout->invoice_id);
    }

    /** 15. Legacy checkout without invoice_id still confirms safely */
    public function test_legacy_checkout_without_invoice_id_confirms_safely(): void
    {
        $ref = 'legacy_checkout_'.uniqid();
        $checkout = PaymentCheckout::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'invoice_id' => null, // legacy checkout without invoice_id
            'reference' => $ref,
            'status' => PaymentCheckout::STATUS_PENDING,
            'amount' => 500000,
            'currency' => 'NGN',
        ]);

        $this->fakePaystackTransactionVerify($ref, $this->org, $this->starterPlan);

        $confirmed = app(PaystackPaymentConfirmationService::class)->confirmReference($ref);

        $this->assertTrue($confirmed);
        $this->assertSame(PaymentCheckout::STATUS_SUCCEEDED, $checkout->fresh()->status);

        // Legacy invoice was created
        $invoice = Invoice::query()->where('payment_reference', $ref)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(500000, $invoice->amount);
    }

    /** 16. New invoice-aware checkout does not create duplicate invoice after success */
    public function test_invoice_aware_checkout_does_not_create_duplicate_invoice_after_success(): void
    {
        $this->fakePaystack();

        // 1. Initial checkout creates authoritative OPEN invoice
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan));

        $this->assertSame(1, Invoice::query()->count());
        $initialInvoice = Invoice::query()->first();
        $this->assertSame('open', $initialInvoice->status);

        $checkout = PaymentCheckout::query()->first();
        $ref = $checkout->reference;

        $this->fakePaystackTransactionVerify($ref, $this->org, $this->starterPlan);

        // 2. Payment confirmation
        $confirmed = app(PaystackPaymentConfirmationService::class)->confirmReference($ref);

        $this->assertTrue($confirmed);
        $this->assertSame(PaymentCheckout::STATUS_SUCCEEDED, $checkout->fresh()->status);

        // Crucial test: NO duplicate invoice created! Exactly 1 invoice remains!
        $this->assertSame(1, Invoice::query()->count());

        $initialInvoice->refresh();
        $this->assertSame('paid', $initialInvoice->status);
        $this->assertSame($ref, $initialInvoice->payment_reference);
        $this->assertNotNull($initialInvoice->paid_at);
        $this->assertSame(500000, $initialInvoice->amount_paid);
        $this->assertSame(0, $initialInvoice->amount_due);
    }

    /** 17. InvoiceIssued dispatched once */
    public function test_invoice_issued_event_is_dispatched_once_and_not_on_idempotent_access(): void
    {
        Event::fake([InvoiceIssued::class]);

        $this->fakePaystack();

        // 1. First checkout: emits InvoiceIssued
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan));

        Event::assertDispatched(InvoiceIssued::class, 1);

        // 2. Second checkout for same plan: idempotent, does NOT emit again
        $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan));

        Event::assertDispatched(InvoiceIssued::class, 1);
    }

    private function fakePaystack(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, '/customer')) {
                return Http::response(['status' => true, 'data' => ['customer_code' => 'CUS_phase2']]);
            }
            if (str_contains($url, '/transaction/initialize')) {
                return Http::response(['status' => true, 'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                ]]);
            }
            if (str_contains($url, '/transaction/verify/')) {
                $path = parse_url($url, PHP_URL_PATH) ?: '';
                $reference = rawurldecode(basename($path));

                return Http::response(['status' => true, 'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 500000,
                    'currency' => 'NGN',
                    'customer' => ['customer_code' => 'CUS_phase2'],
                    'authorization' => [
                        'authorization_code' => 'AUTH_phase2',
                        'reusable' => true,
                        'channel' => 'card',
                    ],
                ]]);
            }
            if (str_contains($url, '/subscription')) {
                return Http::response(['status' => true, 'data' => [
                    'subscription_code' => 'SUB_phase2',
                    'email_token' => 'tok_phase2',
                ]]);
            }

            return Http::response(['status' => false, 'message' => 'unfaked'], 404);
        });
    }

    private function fakePaystackTransactionVerify(string $reference, Organization $org, Plan $plan): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($reference, $org, $plan) {
            $url = $request->url();
            if (str_contains($url, '/transaction/verify/')) {
                return Http::response(['status' => true, 'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => $plan->amount,
                    'currency' => $plan->currency,
                    'metadata' => [
                        'organization_id' => $org->id,
                        'plan_id' => $plan->id,
                        'paystack_plan_code' => $plan->paystack_plan_code,
                    ],
                    'customer' => ['customer_code' => 'CUS_phase2'],
                    'authorization' => [
                        'authorization_code' => 'AUTH_phase2',
                        'reusable' => true,
                        'channel' => 'card',
                    ],
                ]]);
            }
            if (str_contains($url, '/subscription')) {
                return Http::response(['status' => true, 'data' => [
                    'subscription_code' => 'SUB_phase2',
                    'email_token' => 'tok_phase2',
                ]]);
            }

            return Http::response(['status' => false, 'message' => 'unfaked'], 404);
        });
    }
}
