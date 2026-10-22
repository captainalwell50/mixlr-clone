<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Events\InvoicePaid;
use App\Events\InvoicePartiallyPaid;
use App\Events\PaymentAttemptSucceeded;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAttempt;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceSettlementService;
use App\Services\PaystackPaymentConfirmationService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceEnginePhase3Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Plan $starterPlan;
    private Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paystack.secret_key' => 'sk_test_secret']);

        $this->user = User::factory()->create(['is_admin' => false]);
        $this->org = Organization::query()->create([
            'name' => 'Living Waters Media',
            'slug' => 'living-waters-'.uniqid(),
        ]);
        $this->org->users()->attach($this->user->id, ['role' => OrgRole::Owner->value]);

        $this->starterPlan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-'.uniqid(),
            'paystack_plan_code' => 'PLN_starter_phase3',
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
            'paystack_plan_code' => 'PLN_pro_phase3',
            'amount' => 1200000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['live_streaming' => true],
            'is_active' => true,
            'is_custom' => false,
            'sort_order' => 2,
        ]);
    }

    /** 1. Checkout creates PaymentAttempt for authoritative invoice */
    public function test_checkout_creates_payment_attempt_for_authoritative_invoice(): void
    {
        $this->fakePaystack();

        $response = $this->actingAs($this->user)
            ->post(route('billing.checkout', $this->starterPlan));

        $response->assertRedirect('https://checkout.paystack.com/test');

        $invoice = Invoice::query()->first();
        $this->assertNotNull($invoice);
        $this->assertSame('open', $invoice->status);
        $this->assertSame(500000, $invoice->total);

        $attempt = PaymentAttempt::query()->first();
        $this->assertNotNull($attempt);
        $this->assertSame($invoice->id, $attempt->invoice_id);
        $this->assertSame($this->org->id, $attempt->organization_id);
        $this->assertSame(500000, $attempt->amount);
        $this->assertSame('NGN', $attempt->currency);
        $this->assertSame('pending', $attempt->status);
        $this->assertNotNull($attempt->payment_checkout_id);

        $checkout = PaymentCheckout::query()->first();
        $this->assertNotNull($checkout);
        $this->assertSame($checkout->id, $attempt->payment_checkout_id);
        $this->assertSame($attempt->reference, $checkout->reference);
    }

    /** 2. Retry creates another attempt but reuses same invoice */
    public function test_retry_creates_another_attempt_but_reuses_same_invoice(): void
    {
        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(1, PaymentAttempt::query()->count());

        $firstInvoice = Invoice::query()->first();
        $firstAttempt = PaymentAttempt::query()->first();

        // Creator retries checkout for same plan
        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));

        // Invoice is reused, NOT duplicated
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(2, PaymentAttempt::query()->count());

        $secondAttempt = PaymentAttempt::query()->latest('id')->first();
        $this->assertNotSame($firstAttempt->reference, $secondAttempt->reference);
        $this->assertSame($firstInvoice->id, $secondAttempt->invoice_id);
        $this->assertSame('pending', $secondAttempt->status);
    }

    /** 3. Failed attempt remains historical */
    public function test_failed_attempt_remains_historical(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 500000,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        $ref = 'lma_failed_'.uniqid();
        $attempt = PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => $ref,
            'provider' => 'paystack',
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => PaymentAttempt::STATUS_PENDING,
            'initiated_at' => now(),
        ]);

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $result = $confirmation->confirmFromPaystackData([
            'status' => 'failed',
            'reference' => $ref,
            'gateway_response' => 'Insufficient funds',
        ]);

        $this->assertFalse($result);

        $attempt->refresh();
        $this->assertSame(PaymentAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('failed', $attempt->failure_reason);
        $this->assertNotNull($attempt->failed_at);
        $this->assertNull($attempt->confirmed_at);
    }

    /** 4. Failed attempt creates no allocation */
    public function test_failed_attempt_creates_no_allocation(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 500000,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        $ref = 'lma_fail_no_alloc_'.uniqid();
        PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => $ref,
            'provider' => 'paystack',
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => PaymentAttempt::STATUS_PENDING,
            'initiated_at' => now(),
        ]);

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmFromPaystackData([
            'status' => 'failed',
            'reference' => $ref,
        ]);

        $this->assertSame(0, PaymentAllocation::query()->count());
        $this->assertSame(0, Payment::query()->count());

        $invoice->refresh();
        $this->assertSame('open', $invoice->status);
        $this->assertSame(0, $invoice->amount_paid);
        $this->assertSame(500000, $invoice->amount_due);
    }

    /** 5. Successful provider confirmation creates/reuses confirmed Payment */
    public function test_successful_provider_confirmation_creates_or_reuses_confirmed_payment(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 500000,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        $ref = 'lma_succ_'.uniqid();
        $attempt = PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => $ref,
            'provider' => 'paystack',
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => PaymentAttempt::STATUS_PENDING,
            'initiated_at' => now(),
        ]);

        $checkout = PaymentCheckout::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'invoice_id' => $invoice->id,
            'reference' => $ref,
            'status' => PaymentCheckout::STATUS_PENDING,
            'amount' => 500000,
            'currency' => 'NGN',
        ]);
        $attempt->update(['payment_checkout_id' => $checkout->id]);

        $payload = [
            'status' => 'success',
            'reference' => $ref,
            'id' => 'pstk_tx_12345',
            'amount' => 500000,
            'currency' => 'NGN',
            'channel' => 'card',
            'customer' => ['customer_code' => 'CUS_test'],
            'metadata' => [
                'organization_id' => $this->org->id,
                'plan_id' => $this->starterPlan->id,
                'invoice_id' => $invoice->id,
            ],
        ];

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $ok = $confirmation->confirmFromPaystackData($payload);

        $this->assertTrue($ok);
        $this->assertSame(1, Payment::query()->count());

        $payment = Payment::query()->first();
        $this->assertSame($ref, $payment->reference);
        $this->assertSame('pstk_tx_12345', $payment->provider_reference);
        $this->assertSame(500000, $payment->amount);
        $this->assertSame('NGN', $payment->currency);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertNotNull($payment->confirmed_at);
        $this->assertSame($attempt->id, $payment->payment_attempt_id);

        // Re-confirming with same payload reuses the same payment
        $confirmation->confirmFromPaystackData($payload);
        $this->assertSame(1, Payment::query()->count());
    }

    /** 6. Successful payment creates PaymentAllocation */
    public function test_successful_payment_creates_payment_allocation(): void
    {
        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $checkout = PaymentCheckout::query()->first();

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmReference($checkout->reference);

        $this->assertSame(1, PaymentAllocation::query()->count());
        $allocation = PaymentAllocation::query()->first();

        $payment = Payment::query()->first();
        $invoice = Invoice::query()->first();

        $this->assertSame($payment->id, $allocation->payment_id);
        $this->assertSame($invoice->id, $allocation->invoice_id);
        $this->assertSame(500000, $allocation->amount);
        $this->assertNotNull($allocation->allocated_at);
    }

    /** 7. Full allocation sets invoice paid */
    public function test_full_allocation_sets_invoice_paid(): void
    {
        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $checkout = PaymentCheckout::query()->first();

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmReference($checkout->reference);

        $invoice = Invoice::query()->first();
        $this->assertSame('paid', $invoice->status);
        $this->assertTrue($invoice->isPaid());
        $this->assertSame(500000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
    }

    /** 8. paid_at set on first full settlement */
    public function test_paid_at_set_on_first_full_settlement(): void
    {
        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $checkout = PaymentCheckout::query()->first();

        $invoice = Invoice::query()->first();
        $this->assertNull($invoice->paid_at);

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmReference($checkout->reference);

        $invoice->refresh();
        $this->assertNotNull($invoice->paid_at);
        $firstPaidAt = $invoice->paid_at;

        // Idempotent second confirmation preserves original paid_at
        $confirmation->confirmReference($checkout->reference);
        $invoice->refresh();
        $this->assertSame($firstPaidAt->timestamp, $invoice->paid_at->timestamp);
    }

    /** 9. Repeated confirmation does not duplicate Payment */
    public function test_repeated_confirmation_does_not_duplicate_payment(): void
    {
        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $checkout = PaymentCheckout::query()->first();

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmReference($checkout->reference);
        $confirmation->confirmReference($checkout->reference);
        $confirmation->confirmReference($checkout->reference);

        $this->assertSame(1, Payment::query()->count());
    }

    /** 10. Repeated confirmation does not duplicate allocation */
    public function test_repeated_confirmation_does_not_duplicate_allocation(): void
    {
        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $checkout = PaymentCheckout::query()->first();

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmReference($checkout->reference);
        $confirmation->confirmReference($checkout->reference);

        $this->assertSame(1, PaymentAllocation::query()->count());
    }

    /** 11. Repeated confirmation does not fire InvoicePaid twice */
    public function test_repeated_confirmation_does_not_fire_invoice_paid_twice(): void
    {
        Event::fake([InvoicePaid::class]);

        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $checkout = PaymentCheckout::query()->first();

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmReference($checkout->reference);

        Event::assertDispatched(InvoicePaid::class, 1);

        // Second confirmation
        $confirmation->confirmReference($checkout->reference);

        Event::assertDispatched(InvoicePaid::class, 1);
    }

    /** 12. Callback + webhook same reference remains idempotent */
    public function test_callback_and_webhook_same_reference_remains_idempotent(): void
    {
        Event::fake([InvoicePaid::class]);

        $this->fakePaystack();

        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $checkout = PaymentCheckout::query()->first();

        // 1. User returns via Callback
        $response = $this->actingAs($this->user)->get(route('billing.callback', ['reference' => $checkout->reference]));
        $response->assertRedirect(route('creator.home'));

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, PaymentAllocation::query()->count());
        $invoice = Invoice::query()->first();
        $this->assertSame('paid', $invoice->status);
        $firstPaidAt = $invoice->paid_at;

        // 2. Webhook arrives slightly later with same reference
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => $checkout->reference,
                'id' => 'pstk_123',
                'amount' => 500000,
                'currency' => 'NGN',
                'customer' => ['customer_code' => 'CUS_test'],
                'metadata' => [
                    'organization_id' => $this->org->id,
                    'plan_id' => $this->starterPlan->id,
                    'invoice_id' => $invoice->id,
                ],
            ],
        ];

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmFromPaystackData($payload['data']);

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, PaymentAllocation::query()->count());
        $invoice->refresh();
        $this->assertSame($firstPaidAt->timestamp, $invoice->paid_at->timestamp);
        Event::assertDispatched(InvoicePaid::class, 1);
    }

    /** 13. Partial allocation produces partially_paid */
    public function test_partial_allocation_produces_partially_paid(): void
    {
        Event::fake([InvoicePartiallyPaid::class, InvoicePaid::class]);

        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->proPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 1200000,
            'total' => 1200000,
            'amount_paid' => 0,
            'amount_due' => 1200000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        // Confirmed partial payment of 500000 towards 1200000 invoice
        $payment = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'lma_part_'.uniqid(),
            'provider' => 'paystack',
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        $settlement = app(InvoiceSettlementService::class);
        $settlement->settlePayment($payment, $invoice);

        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertSame(500000, $invoice->amount_paid);
        $this->assertSame(700000, $invoice->amount_due);
        $this->assertNull($invoice->paid_at);

        Event::assertDispatched(InvoicePartiallyPaid::class, 1);
        Event::assertNotDispatched(InvoicePaid::class);
    }

    /** 14. amount_paid derived correctly from allocations */
    public function test_amount_paid_derived_correctly_from_allocations(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->proPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 1000000,
            'total' => 1000000,
            'amount_paid' => 0,
            'amount_due' => 1000000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        $payment1 = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'p1_'.uniqid(),
            'amount' => 350000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        $payment2 = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'p2_'.uniqid(),
            'amount' => 250000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        $settlement = app(InvoiceSettlementService::class);
        $settlement->settlePayment($payment1, $invoice);
        $settlement->settlePayment($payment2, $invoice);

        $invoice->refresh();
        $this->assertSame(600000, $invoice->amount_paid);
        $this->assertSame(400000, $invoice->amount_due);
    }

    /** 15. amount_due derived correctly */
    public function test_amount_due_derived_correctly(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 700000,
            'total' => 700000,
            'amount_paid' => 0,
            'amount_due' => 700000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'due_test_'.uniqid(),
            'amount' => 450000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        app(InvoiceSettlementService::class)->settlePayment($payment, $invoice);

        $invoice->refresh();
        $this->assertSame(250000, $invoice->amount_due);
        $this->assertSame(450000, $invoice->amount_paid);
    }

    /** 16. Overpayment does not over-allocate invoice */
    public function test_overpayment_does_not_over_allocate_invoice(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 500000,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        // Overpayment: 800000 confirmed for a 500000 invoice
        $payment = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'overpay_'.uniqid(),
            'amount' => 800000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        app(InvoiceSettlementService::class)->settlePayment($payment, $invoice);

        $invoice->refresh();
        $this->assertSame(500000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
        $this->assertSame('paid', $invoice->status);

        // Exactly 500000 allocated to the invoice
        $allocation = PaymentAllocation::query()->where('invoice_id', $invoice->id)->first();
        $this->assertSame(500000, $allocation->amount);
    }

    /** 17. Unallocated overpayment remains accounted for */
    public function test_unallocated_overpayment_remains_accounted_for(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 500000,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'unalloc_'.uniqid(),
            'amount' => 800000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        app(InvoiceSettlementService::class)->settlePayment($payment, $invoice);

        $this->assertSame(500000, $payment->allocatedAmount());
        $this->assertSame(300000, $payment->unallocatedAmount());
        $this->assertFalse($payment->isFullyAllocated());
    }

    /** 18. Multiple confirmed payments can settle one invoice */
    public function test_multiple_confirmed_payments_can_settle_one_invoice(): void
    {
        Event::fake([InvoicePaid::class, InvoicePartiallyPaid::class]);

        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->startOfDay(),
            'subtotal' => 500000,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
            'issued_at' => now(),
        ]);

        $payment1 = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'part1_'.uniqid(),
            'amount' => 200000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        $payment2 = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'part2_'.uniqid(),
            'amount' => 300000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        $settlement = app(InvoiceSettlementService::class);

        // First payment partially settles
        $settlement->settlePayment($payment1, $invoice);
        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertSame(200000, $invoice->amount_paid);
        $this->assertSame(300000, $invoice->amount_due);
        Event::assertDispatched(InvoicePartiallyPaid::class, 1);
        Event::assertNotDispatched(InvoicePaid::class);

        // Second payment completes settlement
        $settlement->settlePayment($payment2, $invoice);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(500000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
        $this->assertNotNull($invoice->paid_at);
        Event::assertDispatched(InvoicePaid::class, 1);

        $this->assertSame(2, PaymentAllocation::query()->where('invoice_id', $invoice->id)->count());
    }

    /** 19. Failed attempt followed by success settles correctly */
    public function test_failed_attempt_followed_by_success_settles_correctly(): void
    {
        $this->fakePaystack();

        // 1. First checkout attempt
        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $attempt1 = PaymentAttempt::query()->first();
        $invoice = Invoice::query()->first();

        // Mark attempt 1 as failed
        $confirmation = app(PaystackPaymentConfirmationService::class);
        $confirmation->confirmFromPaystackData([
            'status' => 'failed',
            'reference' => $attempt1->reference,
        ]);
        $attempt1->refresh();
        $this->assertSame('failed', $attempt1->status);
        $this->assertSame('open', $invoice->fresh()->status);

        // 2. Creator retries checkout
        $this->actingAs($this->user)->post(route('billing.checkout', $this->starterPlan));
        $this->assertSame(2, PaymentAttempt::query()->count());
        $attempt2 = PaymentAttempt::query()->latest('id')->first();
        $this->assertNotSame($attempt1->reference, $attempt2->reference);

        // Attempt 2 succeeds
        $confirmation->confirmFromPaystackData([
            'status' => 'success',
            'reference' => $attempt2->reference,
            'amount' => 500000,
            'currency' => 'NGN',
            'customer' => ['customer_code' => 'CUS_test'],
            'metadata' => ['invoice_id' => $invoice->id],
        ]);

        $attempt1->refresh();
        $attempt2->refresh();
        $invoice->refresh();

        $this->assertSame('failed', $attempt1->status);
        $this->assertSame('succeeded', $attempt2->status);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(500000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
    }

    /** 20. Issued financial facts remain immutable after settlement */
    public function test_issued_financial_facts_remain_immutable_after_settlement(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $start = Carbon::parse('2026-10-19')->startOfDay();
        $end = Carbon::parse('2026-11-18')->startOfDay();
        $issuedAt = Carbon::parse('2026-10-19 10:00:00');

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'invoice_number' => 'SM-2026-000888',
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'currency' => 'NGN',
            'subtotal' => 500000,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'issued_at' => $issuedAt,
        ]);

        $invoice->lines()->create([
            'description' => 'Starter monthly subscription',
            'quantity' => 1,
            'unit_amount' => 500000,
            'subtotal' => 500000,
            'total' => 500000,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'immut_'.uniqid(),
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        app(InvoiceSettlementService::class)->settlePayment($payment, $invoice);

        $invoice->refresh();

        // Immutable financial facts are strictly preserved:
        $this->assertSame('SM-2026-000888', $invoice->invoice_number);
        $this->assertSame($this->org->id, $invoice->organization_id);
        $this->assertSame($sub->id, $invoice->subscription_id);
        $this->assertSame($this->starterPlan->id, $invoice->plan_id);
        $this->assertSame('2026-10-19', $invoice->billing_period_start->toDateString());
        $this->assertSame('2026-11-18', $invoice->billing_period_end->toDateString());
        $this->assertSame('NGN', $invoice->currency);
        $this->assertSame(500000, $invoice->subtotal);
        $this->assertSame(0, $invoice->discount_total);
        $this->assertSame(0, $invoice->tax_total);
        $this->assertSame(500000, $invoice->total);
        $this->assertSame($issuedAt->timestamp, $invoice->issued_at->timestamp);
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertSame('Starter monthly subscription', $invoice->lines->first()->description);

        // Only settlement fields changed:
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(500000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
        $this->assertNotNull($invoice->paid_at);
    }

    /** 21. Legacy checkout without invoice_id still confirms safely */
    public function test_legacy_checkout_without_invoice_id_still_confirms_safely(): void
    {
        $ref = 'legacy_no_invoice_'.uniqid();
        PaymentCheckout::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'invoice_id' => null, // legacy checkout without invoice_id
            'reference' => $ref,
            'status' => PaymentCheckout::STATUS_PENDING,
            'amount' => $this->starterPlan->amount,
            'currency' => 'NGN',
        ]);

        $confirmation = app(PaystackPaymentConfirmationService::class);
        $ok = $confirmation->confirmFromPaystackData([
            'status' => 'success',
            'reference' => $ref,
            'amount' => 500000,
            'currency' => 'NGN',
            'customer' => ['customer_code' => 'CUS_legacy'],
            'metadata' => ['organization_id' => $this->org->id, 'plan_id' => $this->starterPlan->id],
        ]);

        $this->assertTrue($ok);
        $this->assertSame(1, Payment::query()->where('reference', $ref)->count());
        $this->assertSame(1, PaymentAllocation::query()->count());

        $invoice = Invoice::query()->where('payment_reference', $ref)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(500000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
    }

    /** 22. Active Phase 2 invoice uniqueness remains intact */
    public function test_active_phase2_invoice_uniqueness_remains_intact(): void
    {
        $start = Carbon::parse('2026-10-19')->startOfDay();
        $end = Carbon::parse('2026-11-18')->startOfDay();

        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // First active invoice
        Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'total' => 500000,
            'status' => InvoiceStatus::Open->value,
        ]);

        // Second active invoice for same period must violate SQLite partial unique index
        $this->expectException(QueryException::class);
        Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->proPlan->id,
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'total' => 1200000,
            'status' => InvoiceStatus::Open->value,
        ]);
    }

    /** 23. SQLite constraints enforce duplicate protection */
    public function test_sqlite_constraints_enforce_duplicate_protection(): void
    {
        $ref = 'uniq_ref_'.uniqid();

        Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => $ref,
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        // Duplicate payment reference violation
        try {
            Payment::query()->create([
                'organization_id' => $this->org->id,
                'reference' => $ref,
                'amount' => 500000,
                'currency' => 'NGN',
                'status' => Payment::STATUS_SUCCEEDED,
                'confirmed_at' => now(),
            ]);
            $this->fail('Expected QueryException for duplicate payment reference.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }

        // Duplicate allocation for same (payment_id, invoice_id)
        $payment = Payment::query()->where('reference', $ref)->first();
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 500000,
            'status' => InvoiceStatus::Open->value,
        ]);

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 250000,
            'allocated_at' => now(),
        ]);

        try {
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => 250000,
                'allocated_at' => now(),
            ]);
            $this->fail('Expected QueryException for duplicate (payment_id, invoice_id) allocation.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }
    }

    /** 24. Transaction failure does not leave half-created records */
    public function test_transaction_failure_does_not_leave_half_created_records(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Trialing,
        ]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Open->value,
            'currency' => 'NGN',
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'tx_fail_'.uniqid(),
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($payment, $invoice) {
                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => 500000,
                    'allocated_at' => now(),
                ]);

                // Simulate unexpected crash before commit
                throw new \RuntimeException('Database or network connection failed abruptly.');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Allocation was rolled back cleanly
        $this->assertSame(0, PaymentAllocation::query()->count());
        $invoice->refresh();
        $this->assertSame(0, $invoice->amount_paid);
        $this->assertSame('open', $invoice->status);
    }

    /** 25. Cancelled/void invoice cannot be accidentally settled */
    public function test_cancelled_or_void_invoice_cannot_be_accidentally_settled(): void
    {
        $sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->starterPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $voidInvoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Void->value,
            'currency' => 'NGN',
        ]);

        $cancelledInvoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $sub->id,
            'plan_id' => $this->starterPlan->id,
            'total' => 500000,
            'amount_paid' => 0,
            'amount_due' => 500000,
            'status' => InvoiceStatus::Cancelled->value,
            'currency' => 'NGN',
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $this->org->id,
            'reference' => 'settle_void_'.uniqid(),
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => Payment::STATUS_SUCCEEDED,
            'confirmed_at' => now(),
        ]);

        $settlement = app(InvoiceSettlementService::class);

        // Attempting to settle void invoice must throw DomainException
        try {
            $settlement->settlePayment($payment, $voidInvoice);
            $this->fail('Expected DomainException when settling void invoice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Cannot settle void invoice', $e->getMessage());
        }

        // Attempting to settle cancelled invoice must throw DomainException
        try {
            $settlement->settlePayment($payment, $cancelledInvoice);
            $this->fail('Expected DomainException when settling cancelled invoice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Cannot settle cancelled invoice', $e->getMessage());
        }

        $this->assertSame(0, PaymentAllocation::query()->count());
        $this->assertSame('void', $voidInvoice->fresh()->status);
        $this->assertSame('cancelled', $cancelledInvoice->fresh()->status);
    }

    private function fakePaystack(): void
    {
        $org = $this->org;
        $plan = $this->starterPlan;

        Http::fake(function ($request) use ($org, $plan) {
            $url = $request->url();
            $reference = 'ref_test_'.uniqid();

            if (str_contains($url, '/customer')) {
                return Http::response(['status' => true, 'data' => ['customer_code' => 'CUS_phase3']]);
            }
            if (str_contains($url, '/transaction/initialize')) {
                return Http::response(['status' => true, 'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'acc_phase3',
                    'reference' => $reference,
                ]]);
            }
            if (str_contains($url, '/transaction/verify/')) {
                $refFromUrl = basename(parse_url($url, PHP_URL_PATH));
                return Http::response(['status' => true, 'data' => [
                    'status' => 'success',
                    'reference' => $refFromUrl,
                    'id' => 'pstk_phase3_tx',
                    'amount' => $plan->amount,
                    'currency' => $plan->currency,
                    'metadata' => [
                        'organization_id' => $org->id,
                        'plan_id' => $plan->id,
                        'paystack_plan_code' => $plan->paystack_plan_code,
                    ],
                    'customer' => ['customer_code' => 'CUS_phase3'],
                    'authorization' => [
                        'authorization_code' => 'AUTH_phase3',
                        'reusable' => true,
                        'channel' => 'card',
                    ],
                ]]);
            }
            if (str_contains($url, '/subscription')) {
                return Http::response(['status' => true, 'data' => [
                    'subscription_code' => 'SUB_phase3',
                    'email_token' => 'tok_phase3',
                ]]);
            }

            return Http::response(['status' => false, 'message' => 'unfaked'], 404);
        });
    }
}
