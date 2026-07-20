<?php

namespace App\Http\Controllers;

use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PaystackClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class BillingController extends Controller
{
    public function plans(Request $request): View|RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        if ($organization === null) {
            return redirect()->route('onboarding.show');
        }

        $organization->load('subscription.plan');

        return view('billing.plans', [
            'organization' => $organization,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'subscription' => $organization->subscription,
            'paystackEnabled' => app(PaystackClient::class)->enabled(),
        ]);
    }

    public function startTrial(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($organization !== null, 403);

        $subscription = $organization->subscription;
        if ($subscription === null) {
            Subscription::query()->create([
                'organization_id' => $organization->id,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays(7),
            ]);
        } elseif (! $subscription->allowsBroadcast()) {
            $subscription->update([
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays(7),
                'ends_at' => null,
            ]);
        }

        return redirect()->route('creator.home')
            ->with('status', __('Your 7-day trial is active. Open Studio when you’re ready.'));
    }

    public function checkout(Request $request, Plan $plan, PaystackClient $paystack): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($organization !== null, 403);
        abort_unless($plan->is_active, 404);

        $user = $request->user();

        if (! $paystack->enabled()) {
            return $this->activateWithoutPaystack($organization, $plan);
        }

        if (! filled($plan->paystack_plan_code)) {
            return back()->with('error', __('This plan is not linked to Paystack yet. Contact support.'));
        }

        try {
            $customerCode = $organization->paystack_customer_code;
            if (! $customerCode) {
                $customer = $paystack->createCustomer($user->email, $user->name);
                $customerCode = $customer['customer_code'] ?? null;
                if ($customerCode) {
                    $organization->update(['paystack_customer_code' => $customerCode]);
                }
            }

            $reference = 'lma_'.$organization->id.'_'.now()->timestamp.'_'.bin2hex(random_bytes(4));

            $data = $paystack->initializeTransaction([
                'email' => $user->email,
                'amount' => $plan->amount,
                'currency' => $plan->currency,
                'plan' => $plan->paystack_plan_code,
                'reference' => $reference,
                'callback_url' => route('billing.callback'),
                'metadata' => [
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'cancel_action' => route('billing.plans'),
                ],
            ]);

            $authUrl = $data['authorization_url'] ?? null;
            if (! is_string($authUrl) || $authUrl === '') {
                throw new RuntimeException('Paystack did not return a checkout URL.');
            }

            session([
                'billing.checkout_reference' => $reference,
                'billing.plan_id' => $plan->id,
                'billing.organization_id' => $organization->id,
            ]);

            return redirect()->away($authUrl);
        } catch (Throwable $e) {
            Log::warning('Paystack checkout failed', ['error' => $e->getMessage()]);

            return back()->with('error', __('Could not start Paystack checkout. Try again or continue on trial.'));
        }
    }

    public function callback(Request $request, PaystackClient $paystack): RedirectResponse
    {
        $reference = (string) $request->query('reference', session('billing.checkout_reference', ''));
        $organization = $this->ownedOrganization($request);
        abort_unless($organization !== null, 403);

        if ($reference === '') {
            return redirect()->route('billing.plans')
                ->with('error', __('Missing payment reference.'));
        }

        if (! $paystack->enabled()) {
            return redirect()->route('creator.home');
        }

        try {
            $data = $paystack->verifyTransaction($reference);
        } catch (Throwable $e) {
            Log::warning('Paystack verify failed', ['error' => $e->getMessage()]);

            return redirect()->route('billing.plans')
                ->with('error', __('Payment could not be verified. If you were charged, it will activate shortly.'));
        }

        if (($data['status'] ?? '') !== 'success') {
            return redirect()->route('billing.plans')
                ->with('error', __('Payment was not completed.'));
        }

        $planId = (int) (data_get($data, 'metadata.plan_id') ?: session('billing.plan_id'));
        $plan = Plan::query()->find($planId);

        $this->activateSubscription(
            $organization,
            $plan,
            data_get($data, 'customer.customer_code'),
            data_get($data, 'subscription.subscription_code') ?? data_get($data, 'subscription_code'),
            data_get($data, 'subscription.email_token') ?? data_get($data, 'email_token'),
        );

        session()->forget(['billing.checkout_reference', 'billing.plan_id', 'billing.organization_id']);

        return redirect()->route('creator.home')
            ->with('status', __('Subscription activated. You’re ready to broadcast.'));
    }

    protected function activateWithoutPaystack(Organization $organization, Plan $plan): RedirectResponse
    {
        $this->activateSubscription($organization, $plan);

        return redirect()->route('creator.home')
            ->with('status', __('Plan activated (Paystack not configured — local/dev mode).'));
    }

    protected function activateSubscription(
        Organization $organization,
        ?Plan $plan,
        ?string $customerCode = null,
        ?string $subscriptionCode = null,
        ?string $emailToken = null,
    ): void {
        if ($customerCode) {
            $organization->update(['paystack_customer_code' => $customerCode]);
        }

        $subscription = $organization->subscription;
        $payload = [
            'plan_id' => $plan?->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'ends_at' => null,
            'paystack_customer_code' => $customerCode ?? $organization->paystack_customer_code,
            'paystack_subscription_code' => $subscriptionCode,
            'paystack_email_token' => $emailToken,
        ];

        if ($subscription) {
            $subscription->update(array_filter($payload, fn ($v) => $v !== null) + [
                'plan_id' => $plan?->id,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => null,
                'ends_at' => null,
            ]);
        } else {
            Subscription::query()->create([
                'organization_id' => $organization->id,
                ...$payload,
            ]);
        }
    }

    protected function ownedOrganization(Request $request): ?Organization
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return $user->organizations()
            ->wherePivot('role', OrgRole::Owner->value)
            ->with('subscription')
            ->first()
            ?? $user->organizations()->with('subscription')->first();
    }
}
