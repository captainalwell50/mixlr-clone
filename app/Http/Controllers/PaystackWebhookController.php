<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PaystackClient;
use App\Services\PaystackPaymentConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackClient $paystack, PaystackPaymentConfirmationService $confirmation): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        if (! $paystack->verifyWebhookSignature($payload, $signature)) {
            return response('Invalid signature', 400);
        }

        $event = $request->input('event');
        $data = $request->input('data', []);

        match ($event) {
            'subscription.create', 'subscription.enable' => $this->handleSubscriptionActive($data),
            'charge.success' => $this->handleChargeSuccess($data, $confirmation),
            'invoice.payment_failed' => $this->handlePaymentFailed($data),
            'subscription.disable' => $this->handleSubscriptionDisable($data),
            'refund.processed', 'charge.dispute.create' => $this->handleRefund($data),
            default => Log::info('Paystack webhook ignored', ['event' => $event]),
        };

        return response('OK', 200);
    }

    protected function handleRefund(array $data): void
    {
        $reference = (string) ($data['transaction_reference'] ?? $data['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $reason = (string) ($data['deduction_for'] ?? $data['reason'] ?? 'Paystack refund/dispute event');
        $reversalRef = isset($data['id']) ? (string) $data['id'] : (isset($data['refund_id']) ? (string) $data['refund_id'] : null);
        $amountMinor = isset($data['amount']) && is_numeric($data['amount']) ? (int) $data['amount'] : null;

        event(new \App\Events\SubscriptionPaymentReversed(
            paymentReference: $reference,
            reversalReference: $reversalRef,
            amountMinor: $amountMinor,
            reason: $reason,
            reversedAt: now(),
            metadata: [
                'provider' => 'paystack',
                'deduction_for' => $data['deduction_for'] ?? null,
            ],
        ));
    }

    protected function handleSubscriptionActive(array $data): void
    {
        $organization = $this->findOrganization($data);
        if ($organization === null) {
            return;
        }

        $plan = $this->findPlan($data);
        $this->upsertSubscription($organization, [
            'plan_id' => $plan?->id,
            'status' => SubscriptionStatus::Active,
            'paystack_subscription_code' => $data['subscription_code'] ?? null,
            'paystack_email_token' => $data['email_token'] ?? null,
            'paystack_customer_code' => data_get($data, 'customer.customer_code'),
            'trial_ends_at' => null,
            'ends_at' => null,
            'renews_at' => $this->renewsAtFromPayload($data, $plan, $organization->subscription),
        ]);
    }

    protected function handleChargeSuccess(array $data, PaystackPaymentConfirmationService $confirmation): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $libraryCheckout = $reference !== ''
            && \App\Models\DigitalLibraryCheckout::query()->where('provider_reference', $reference)->exists();
        if ($libraryCheckout || data_get($data, 'metadata.purpose') === 'digital_library') {
            app(\App\Services\DigitalLibraryPurchaseService::class)->confirmFromPaystackData($data);

            return;
        }

        $confirmation->confirmFromPaystackData($data);
    }

    protected function handlePaymentFailed(array $data): void
    {
        $organization = $this->findOrganization($data);
        if ($organization === null) {
            return;
        }

        $this->upsertSubscription($organization, [
            'status' => SubscriptionStatus::PastDue,
        ]);

        try {
            $invoiceCode = (string) ($data['invoice_code'] ?? $data['reference'] ?? now()->format('Ymd'));
            $plan = $organization->subscription?->plan;
            $owners = $organization->users()
                ->wherePivot('role', \App\Enums\OrgRole::Owner->value)
                ->get();
            if ($owners->isEmpty()) {
                $owners = $organization->users;
            }

            $outbound = app(\App\Services\OutboundEmailService::class);
            foreach ($owners as $user) {
                $outbound->sendOperational(
                    mailable: new \App\Mail\PaymentFailedMail($organization, $plan),
                    recipient: $user->email,
                    type: 'payment_failed',
                    eventKey: "payment-failed:{$organization->id}:{$invoiceCode}:{$user->id}",
                    user: $user,
                    metadata: [
                        'organization_id' => $organization->id,
                        'invoice_code' => $invoiceCode,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Payment failed email dispatch failed', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleSubscriptionDisable(array $data): void
    {
        $organization = $this->findOrganization($data);
        if ($organization === null) {
            return;
        }

        $this->upsertSubscription($organization, [
            'status' => SubscriptionStatus::Cancelled,
            'ends_at' => now(),
            'renews_at' => null,
        ]);

        try {
            $subCode = (string) ($data['subscription_code'] ?? $organization->subscription?->paystack_subscription_code ?? 'disabled');
            $owners = $organization->users()
                ->wherePivot('role', \App\Enums\OrgRole::Owner->value)
                ->get();
            if ($owners->isEmpty()) {
                $owners = $organization->users;
            }

            $outbound = app(\App\Services\OutboundEmailService::class);
            foreach ($owners as $user) {
                $outbound->sendOperational(
                    mailable: new \App\Mail\SubscriptionCancelledMail($organization),
                    recipient: $user->email,
                    type: 'subscription_cancelled',
                    eventKey: "subscription-cancelled:{$organization->id}:{$subCode}:{$user->id}",
                    user: $user,
                    metadata: [
                        'organization_id' => $organization->id,
                        'subscription_code' => $subCode,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Subscription cancelled email dispatch failed', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findOrganization(array $data): ?Organization
    {
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

            $bySub = Subscription::query()->where('paystack_customer_code', $customerCode)->first();

            return $bySub?->organization;
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

    protected function findPlan(array $data): ?Plan
    {
        $planCode = data_get($data, 'plan.plan_code') ?? data_get($data, 'plan_code');
        if (! is_string($planCode) || $planCode === '') {
            return null;
        }

        return Plan::query()->where('paystack_plan_code', $planCode)->first();
    }

    protected function renewsAtFromPayload(array $data, ?Plan $plan, ?Subscription $existing): ?\Carbon\CarbonInterface
    {
        $raw = $data['next_payment_date']
            ?? data_get($data, 'subscription.next_payment_date');

        if (is_string($raw) && $raw !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($raw);
            } catch (\Throwable) {
            }
        }

        if ($plan) {
            return $plan->periodEndFrom(now());
        }

        $existing?->loadMissing('plan');

        return $existing?->plan?->periodEndFrom(now())
            ?? $existing?->renews_at;
    }

    protected function upsertSubscription(Organization $organization, array $payload): void
    {
        if (! empty($payload['paystack_customer_code'])) {
            $organization->update([
                'paystack_customer_code' => $payload['paystack_customer_code'],
            ]);
        }

        $subscription = $organization->subscription;
        if ($subscription) {
            $subscription->update($payload);
        } else {
            Subscription::query()->create([
                'organization_id' => $organization->id,
                'status' => $payload['status'] ?? SubscriptionStatus::Active,
                ...$payload,
            ]);
        }
    }
}
