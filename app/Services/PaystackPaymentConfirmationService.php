<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAttempt;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackPaymentConfirmationService
{
    private InvoiceSettlementService $settlement;

    public function __construct(
        private PaystackClient $paystack,
        private SubscriptionAssignmentService $assignments,
        ?InvoiceSettlementService $settlement = null,
    ) {
        $this->settlement = $settlement ?? app(InvoiceSettlementService::class);
    }

    public function recordPending(
        Organization $organization,
        Plan $plan,
        string $reference,
        \App\Models\Invoice|int|null $invoice = null,
    ): PaymentCheckout {
        $invoiceId = $invoice instanceof \App\Models\Invoice ? $invoice->id : $invoice;

        $checkout = PaymentCheckout::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoiceId,
                'status' => PaymentCheckout::STATUS_PENDING,
                'amount' => $plan->amount,
                'currency' => $plan->currency,
                'failure_reason' => null,
                'verified_at' => null,
            ],
        );

        if ($invoiceId) {
            PaymentAttempt::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'invoice_id' => $invoiceId,
                    'organization_id' => $organization->id,
                    'payment_checkout_id' => $checkout->id,
                    'provider' => 'paystack',
                    'amount' => $plan->amount,
                    'currency' => $plan->currency ?? 'NGN',
                    'status' => PaymentAttempt::STATUS_PENDING,
                    'initiated_at' => now(),
                ]
            );
        }

        return $checkout;
    }

    public function confirmReference(string $reference): bool
    {
        if ($reference === '' || ! $this->paystack->enabled()) {
            return false;
        }

        $existing = PaymentCheckout::query()->where('reference', $reference)->first();
        if ($existing?->status === PaymentCheckout::STATUS_SUCCEEDED) {
            return true;
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);
        } catch (Throwable $e) {
            $this->touchCheckout($reference, $e->getMessage());
            Log::warning('Paystack verify failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return $this->confirmFromPaystackData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmFromPaystackData(array $data): bool
    {
        $reference = (string) ($data['reference'] ?? '');
        if (data_get($data, 'metadata.purpose') === 'digital_library') {
            return false;
        }
        if ($reference !== '' && \App\Models\DigitalLibraryCheckout::query()->where('provider_reference', $reference)->exists()) {
            return false;
        }
        $paystackStatus = strtolower((string) ($data['status'] ?? ''));

        $checkout = $reference !== ''
            ? PaymentCheckout::query()->where('reference', $reference)->first()
            : null;

        $providerRef = (string) (data_get($data, 'id') ?? $reference);
        $attempt = $reference !== ''
            ? PaymentAttempt::query()->where('reference', $reference)->first()
            : null;

        if ($paystackStatus !== 'success') {
            $this->markUnsuccessful($checkout, $paystackStatus, $reference);
            if ($attempt !== null) {
                $attempt->markFailed($paystackStatus !== '' ? $paystackStatus : 'Payment unsuccessful', $data);
            }

            return false;
        }

        $organization = $this->resolveOrganization($data, $checkout);
        if ($organization === null) {
            $this->touchCheckout($reference, 'organization not found');
            Log::warning('Paystack confirmation missing organization', ['reference' => $reference]);

            return false;
        }

        $plan = $this->resolvePlan($data, $checkout);
        if ($plan === null || $plan->isFree()) {
            $this->touchCheckout($reference, 'plan not found');
            Log::warning('Paystack confirmation missing plan', ['reference' => $reference]);

            return false;
        }

        $paystackSubscription = $this->ensurePaystackSubscription($data, $plan);

        DB::transaction(function () use ($organization, $plan, $data, $checkout, $attempt, $paystackStatus, $paystackSubscription, $reference, $providerRef): void {
            $organization->refresh();

            $renewsAt = $this->renewsAtFromPayload($data, $plan);
            $this->assignments->assignPaid($organization, $plan, [
                'paystack_customer_code' => data_get($data, 'customer.customer_code'),
                'paystack_subscription_code' => $paystackSubscription['subscription_code']
                    ?? data_get($data, 'subscription.subscription_code')
                    ?? data_get($data, 'subscription_code'),
                'paystack_email_token' => $paystackSubscription['email_token']
                    ?? data_get($data, 'subscription.email_token')
                    ?? data_get($data, 'email_token'),
                'renews_at' => $renewsAt,
            ]);

            $customerCode = data_get($data, 'customer.customer_code');
            if (is_string($customerCode) && $customerCode !== '') {
                $organization->update(['paystack_customer_code' => $customerCode]);
            }

            if ($checkout) {
                $checkout->update([
                    'plan_id' => $plan->id,
                    'status' => PaymentCheckout::STATUS_SUCCEEDED,
                    'paystack_status' => $paystackStatus,
                    'failure_reason' => null,
                    'last_verified_at' => now(),
                    'verified_at' => now(),
                    'attempts' => $checkout->attempts + 1,
                ]);
            }

            if ($attempt !== null) {
                $attempt->markSucceeded($providerRef, $data);
            }

            $isRenewal = ! empty(data_get($data, 'subscription.subscription_code'))
                || ! empty(data_get($data, 'subscription_code'))
                || ($checkout === null);

            $grossAmount = (int) data_get($data, 'amount', $plan->amount);
            $currency = strtoupper((string) data_get($data, 'currency', $plan->currency ?? 'NGN'));

            event(new \App\Events\SubscriptionPaymentConfirmed(
                organizationId: $organization->id,
                planId: $plan->id,
                paymentReference: $reference,
                amountMinor: $grossAmount,
                currency: $currency,
                confirmedAt: now(),
                isRenewal: $isRenewal,
                paymentCheckoutId: $checkout?->id,
                providerReference: $providerRef,
                metadata: [
                    'provider' => 'paystack',
                    'customer_code' => data_get($data, 'customer.customer_code'),
                    'channel' => data_get($data, 'channel'),
                ],
            ));

            // Phase 3: Confirmed Payment record (idempotent retrieval/creation)
            $payment = Payment::query()->where('reference', $reference)->first();
            if ($payment === null) {
                $payment = Payment::query()->create([
                    'organization_id' => $organization->id,
                    'payment_attempt_id' => $attempt?->id,
                    'reference' => $reference,
                    'provider_reference' => $providerRef,
                    'provider' => 'paystack',
                    'amount' => $grossAmount,
                    'currency' => $currency,
                    'status' => Payment::STATUS_SUCCEEDED,
                    'confirmed_at' => now(),
                    'provider_payload' => [
                        'customer_code' => data_get($data, 'customer.customer_code'),
                        'channel' => data_get($data, 'channel'),
                        'plan_code' => data_get($data, 'plan.plan_code'),
                    ],
                ]);

                if ($attempt !== null) {
                    \App\Events\PaymentAttemptSucceeded::dispatch($attempt, $payment);
                }
            }

            try {
                $amountRaw = data_get($data, 'amount');
                $amountPaid = is_numeric($amountRaw) ? ((float) $amountRaw) / 100 : (float) $plan->amount;

                // Phase 2/3: Resolve target invoice
                $invoice = null;
                if ($checkout?->invoice_id) {
                    $invoice = Invoice::query()->find($checkout->invoice_id);
                } elseif ($attempt?->invoice_id) {
                    $invoice = Invoice::query()->find($attempt->invoice_id);
                } elseif (data_get($data, 'metadata.invoice_id')) {
                    $invoice = Invoice::query()->find(data_get($data, 'metadata.invoice_id'));
                }

                if ($invoice !== null) {
                    if ($invoice->payment_reference === null) {
                        $invoice->payment_reference = $reference;
                        $invoice->save();
                    }
                    // Authoritative settlement derived from payment allocation
                    $this->settlement->settlePayment($payment, $invoice);
                } else {
                    // Legacy fallback path: checkout without invoice_id
                    $legacyInvoice = Invoice::query()->firstOrCreate(
                        ['payment_reference' => $reference],
                        [
                            'organization_id' => $organization->id,
                            'subscription_id' => $organization->subscription?->id,
                            'invoice_number' => 'INV-'.strtoupper(substr($reference, 0, 12)),
                            'amount' => $grossAmount,
                            'currency' => $currency,
                            'due_date' => now(),
                            'paid_at' => now(),
                            'status' => Invoice::STATUS_PAID,
                            'subtotal' => $grossAmount,
                            'total' => $grossAmount,
                            'amount_paid' => $grossAmount,
                            'amount_due' => 0,
                        ]
                    );

                    $this->settlement->settlePayment($payment, $legacyInvoice);
                }

                $owners = $organization->users()
                    ->wherePivot('role', \App\Enums\OrgRole::Owner->value)
                    ->get();
                if ($owners->isEmpty()) {
                    $owners = $organization->users;
                }

                $outbound = app(\App\Services\OutboundEmailService::class);
                foreach ($owners as $user) {
                    $outbound->sendOperational(
                        mailable: new \App\Mail\PaymentReceiptMail(
                            organization: $organization,
                            plan: $plan,
                            amount: $amountPaid,
                            currency: $currency,
                            paymentReference: $reference,
                            paymentDate: now()
                        ),
                        recipient: $user->email,
                        type: 'payment_receipt',
                        eventKey: "payment-receipt:{$reference}:{$user->id}",
                        user: $user,
                        metadata: [
                            'organization_id' => $organization->id,
                            'plan_id' => $plan->id,
                            'reference' => $reference,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Payment receipt dispatch failed', [
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return true;
    }

    public function confirmPending(?Organization $organization = null, bool $immediate = false): int
    {
        if (! $this->paystack->enabled()) {
            return 0;
        }

        $confirmed = 0;
        $grace = $immediate ? 0 : (int) config('billing.confirm_grace_seconds', 90);
        $lookback = (int) config('billing.confirm_lookback_days', 3);
        $retryAfter = $immediate ? 0 : (int) config('billing.confirm_retry_seconds', 90);

        $query = PaymentCheckout::query()
            ->pending()
            ->where('created_at', '<=', now()->subSeconds(max(0, $grace)))
            ->where('created_at', '>=', now()->subDays(max(1, $lookback)))
            ->where(function ($query) use ($retryAfter): void {
                $query->whereNull('last_verified_at')
                    ->orWhere('last_verified_at', '<=', now()->subSeconds(max(30, $retryAfter)));
            })
            ->orderBy('id')
            ->limit(50);

        if ($organization) {
            $query->where('organization_id', $organization->id);
        }

        foreach ($query->get() as $checkout) {
            if ($this->confirmReference($checkout->reference)) {
                $confirmed++;
            }
        }

        return $confirmed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveOrganization(array $data, ?PaymentCheckout $checkout): ?Organization
    {
        if ($checkout?->organization) {
            return $checkout->organization;
        }

        $orgId = data_get($data, 'metadata.organization_id');
        if ($orgId) {
            return Organization::query()->find($orgId);
        }

        $customerCode = data_get($data, 'customer.customer_code')
            ?? data_get($data, 'customer_code');
        if (is_string($customerCode) && $customerCode !== '') {
            $byOrg = Organization::query()->where('paystack_customer_code', $customerCode)->first();
            if ($byOrg) {
                return $byOrg;
            }

            return Subscription::query()->where('paystack_customer_code', $customerCode)->first()?->organization;
        }

        $subCode = $data['subscription_code'] ?? data_get($data, 'subscription.subscription_code');
        if (is_string($subCode) && $subCode !== '') {
            return Subscription::query()
                ->where('paystack_subscription_code', $subCode)
                ->first()
                ?->organization;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolvePlan(array $data, ?PaymentCheckout $checkout): ?Plan
    {
        $planId = data_get($data, 'metadata.plan_id') ?: $checkout?->plan_id;
        if ($planId) {
            $plan = Plan::query()->find($planId);
            if ($plan) {
                return $plan;
            }
        }

        $planCode = data_get($data, 'plan.plan_code')
            ?? data_get($data, 'plan_code')
            ?? data_get($data, 'metadata.paystack_plan_code');
        if (is_string($planCode) && $planCode !== '') {
            $plan = Plan::query()->where('paystack_plan_code', $planCode)->first();
            if ($plan) {
                return $plan;
            }
        }

        $existing = $checkout?->organization?->subscription?->plan
            ?? $this->resolveOrganization($data, $checkout)?->subscription?->plan;

        return $existing && ! $existing->isFree() ? $existing : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renewsAtFromPayload(array $data, Plan $plan): Carbon
    {
        $raw = $data['next_payment_date']
            ?? data_get($data, 'subscription.next_payment_date');

        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (Throwable) {
            }
        }

        return $plan->periodEndFrom(now());
    }

    protected function markUnsuccessful(?PaymentCheckout $checkout, string $paystackStatus, string $reference): void
    {
        $terminal = in_array($paystackStatus, ['failed', 'reversed'], true)
            || ($paystackStatus === 'abandoned' && $checkout?->created_at?->lte(now()->subHours(6)));

        if ($reference !== '') {
            $attempt = PaymentAttempt::query()->where('reference', $reference)->first();
            if ($attempt !== null) {
                if ($terminal) {
                    $attempt->markFailed($paystackStatus !== '' ? $paystackStatus : 'Payment failed');
                } elseif ($paystackStatus === 'abandoned') {
                    $attempt->markAbandoned();
                }
            }
        }

        if ($checkout === null) {
            $this->touchCheckout($reference, $paystackStatus !== '' ? $paystackStatus : 'not success');

            return;
        }

        $checkout->update([
            'status' => $terminal
                ? ($paystackStatus === 'abandoned' ? PaymentCheckout::STATUS_ABANDONED : PaymentCheckout::STATUS_FAILED)
                : PaymentCheckout::STATUS_PENDING,
            'paystack_status' => $paystackStatus !== '' ? $paystackStatus : null,
            'failure_reason' => $terminal ? $paystackStatus : null,
            'last_verified_at' => now(),
            'attempts' => $checkout->attempts + 1,
        ]);
    }

    protected function touchCheckout(string $reference, string $reason): void
    {
        if ($reference === '') {
            return;
        }

        PaymentCheckout::query()->where('reference', $reference)->increment('attempts', 1, [
            'last_verified_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    /**
     * One-time checkout does not attach a Paystack plan. After a reusable
     * authorization (usually card), start auto-renew on the next cycle.
     *
     * @param  array<string, mixed>  $data
     * @return array{subscription_code?: string, email_token?: string}
     */
    protected function ensurePaystackSubscription(array $data, Plan $plan): array
    {
        $existingCode = data_get($data, 'subscription.subscription_code')
            ?? data_get($data, 'subscription_code');
        if (is_string($existingCode) && $existingCode !== '') {
            return [
                'subscription_code' => $existingCode,
                'email_token' => (string) (data_get($data, 'subscription.email_token') ?? data_get($data, 'email_token') ?? ''),
            ];
        }

        $planCode = $plan->paystack_plan_code;
        $customer = data_get($data, 'customer.customer_code')
            ?? data_get($data, 'customer.email');
        $authorization = data_get($data, 'authorization.authorization_code');
        $reusable = (bool) data_get($data, 'authorization.reusable', false);

        if (! filled($planCode) || ! is_string($customer) || $customer === '' || ! is_string($authorization) || $authorization === '' || ! $reusable) {
            return [];
        }

        try {
            $created = $this->paystack->createSubscription([
                'customer' => $customer,
                'plan' => $planCode,
                'authorization' => $authorization,
                'start_date' => $plan->periodEndFrom(now())->toIso8601String(),
            ]);

            return [
                'subscription_code' => (string) ($created['subscription_code'] ?? ''),
                'email_token' => (string) ($created['email_token'] ?? ''),
            ];
        } catch (Throwable $e) {
            Log::warning('Paystack subscription create failed', [
                'plan' => $planCode,
                'customer' => $customer,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
