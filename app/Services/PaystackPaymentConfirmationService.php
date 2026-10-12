<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackPaymentConfirmationService
{
    public function __construct(
        private PaystackClient $paystack,
        private SubscriptionAssignmentService $assignments,
    ) {}

    public function recordPending(Organization $organization, Plan $plan, string $reference): PaymentCheckout
    {
        return PaymentCheckout::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => PaymentCheckout::STATUS_PENDING,
                'amount' => $plan->amount,
                'currency' => $plan->currency,
                'failure_reason' => null,
                'verified_at' => null,
            ],
        );
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

        if ($paystackStatus !== 'success') {
            $this->markUnsuccessful($checkout, $paystackStatus, $reference);

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

        DB::transaction(function () use ($organization, $plan, $data, $checkout, $paystackStatus, $paystackSubscription, $reference): void {
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
                providerReference: (string) (data_get($data, 'id') ?? $reference),
                metadata: [
                    'provider' => 'paystack',
                    'customer_code' => data_get($data, 'customer.customer_code'),
                    'channel' => data_get($data, 'channel'),
                ],
            ));

            try {
                $amountRaw = data_get($data, 'amount');
                $amountPaid = is_numeric($amountRaw) ? ((float) $amountRaw) / 100 : (float) $plan->amount;
                $currency = (string) (data_get($data, 'currency') ?? $plan->currency ?? 'NGN');

                \App\Models\Invoice::query()->firstOrCreate(
                    ['payment_reference' => $reference],
                    [
                        'organization_id' => $organization->id,
                        'subscription_id' => $organization->subscription?->id,
                        'invoice_number' => 'INV-'.strtoupper(substr($reference, 0, 12)),
                        'amount' => (int) ($amountPaid * 100),
                        'currency' => $currency,
                        'due_date' => now(),
                        'paid_at' => now(),
                        'status' => \App\Models\Invoice::STATUS_PAID,
                    ]
                );

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
