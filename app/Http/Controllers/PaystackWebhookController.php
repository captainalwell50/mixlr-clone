<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PaystackClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackClient $paystack): Response
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
            'charge.success' => $this->handleChargeSuccess($data),
            'invoice.payment_failed' => $this->handlePaymentFailed($data),
            'subscription.disable' => $this->handleSubscriptionDisable($data),
            default => Log::info('Paystack webhook ignored', ['event' => $event]),
        };

        return response('OK', 200);
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
        ]);
    }

    protected function handleChargeSuccess(array $data): void
    {
        $organization = $this->findOrganization($data);
        if ($organization === null) {
            return;
        }

        $planId = data_get($data, 'metadata.plan_id');
        $plan = $planId ? Plan::query()->find($planId) : $this->findPlan($data);

        $this->upsertSubscription($organization, [
            'plan_id' => $plan?->id ?? $organization->subscription?->plan_id,
            'status' => SubscriptionStatus::Active,
            'paystack_customer_code' => data_get($data, 'customer.customer_code'),
            'paystack_subscription_code' => data_get($data, 'subscription.subscription_code')
                ?? $organization->subscription?->paystack_subscription_code,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);
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
        ]);
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
