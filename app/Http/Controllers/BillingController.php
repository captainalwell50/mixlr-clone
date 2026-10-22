<?php

namespace App\Http\Controllers;

use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\FreePeriodService;
use App\Services\InvoiceGenerationService;
use App\Services\PaystackClient;
use App\Services\PaystackPaymentConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class BillingController extends Controller
{
    public function plans(Request $request, PaystackPaymentConfirmationService $confirmation): View|RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        if ($organization === null) {
            return redirect()->route('onboarding.show');
        }

        $organization->load('subscription.plan');
        $confirmation->confirmPending($organization, immediate: true);
        $organization->load('subscription.plan');

        return view('billing.plans', [
            'organization' => $organization,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'subscription' => $organization->subscription,
            'paystackEnabled' => app(PaystackClient::class)->enabled(),
            'pendingCheckout' => $organization->paymentCheckouts()
                ->pending()
                ->latest('id')
                ->first(),
        ]);
    }

    public function startTrial(Request $request, FreePeriodService $freePeriod): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($organization !== null, 403);

        $subscription = $organization->subscription;
        $endsAt = $freePeriod->windowEndsAt($organization, $subscription);

        if ($subscription?->trial_ends_at !== null && $subscription->trial_ends_at->isPast()) {
            return redirect()->route('billing.plans')
                ->with('error', __('Your free period has ended. Subscribe to keep broadcasting.'));
        }

        if ($endsAt->isPast()) {
            return redirect()->route('billing.plans')
                ->with('error', __('Your free period has ended. Subscribe to keep broadcasting.'));
        }

        if ($subscription === null) {
            Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $freePeriod->ensureFreePlan()->id,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $endsAt,
            ]);
        } elseif ($subscription->trial_ends_at === null && $subscription->isOnFreePeriod()) {
            $subscription->update([
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $endsAt,
                'ends_at' => null,
            ]);
        } elseif (! $subscription->allowsBroadcast()) {
            return redirect()->route('billing.plans')
                ->with('error', __('Your free period has ended. Subscribe to keep broadcasting.'));
        }

        return redirect()->route('creator.home')
            ->with('status', __('Your :days-day free access is active. Open Studio when you’re ready.', [
                'days' => $freePeriod->freeDays(),
            ]));
    }

    public function checkout(
        Request $request,
        Plan $plan,
        PaystackClient $paystack,
        FreePeriodService $freePeriod,
        PaystackPaymentConfirmationService $confirmation,
        InvoiceGenerationService $invoiceGenerator,
    ): RedirectResponse {
        $organization = $this->ownedOrganization($request);
        abort_unless($organization !== null, 403);
        abort_unless($plan->is_active, 404);

        $user = $request->user();

        if ($plan->isFree()) {
            return $this->activateFreePlan($organization, $plan, $freePeriod);
        }

        if ($plan->isCustom()) {
            return redirect()->route('billing.plans')
                ->with('error', __('Custom packages are set up with sales. Email :email to get started.', [
                    'email' => config('app.support_email'),
                ]));
        }

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

            // Phase 2: Establish subscription billing context & authoritative OPEN invoice
            $subscription = $organization->subscription;
            if ($subscription === null) {
                $subscription = Subscription::query()->create([
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Trialing,
                ]);
                $organization->setRelation('subscription', $subscription);
            }

            $invoice = $invoiceGenerator->generateForCheckout($subscription, $plan);

            $reference = 'lma_'.$organization->id.'_'.now()->timestamp.'_'.bin2hex(random_bytes(4));

            // Phase 3: Create PaymentAttempt and PaymentCheckout before provider initialization
            $confirmation->recordPending($organization, $plan, $reference, $invoice);

            $data = $paystack->initializeTransaction([
                'email' => $user->email,
                'amount' => $plan->amount,
                'currency' => $plan->currency,
                'reference' => $reference,
                'callback_url' => route('billing.callback'),
                'channels' => config('billing.paystack_channels'),
                'metadata' => [
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'invoice_id' => $invoice->id,
                    'paystack_plan_code' => $plan->paystack_plan_code,
                    'cancel_action' => route('billing.plans'),
                ],
            ]);

            $authUrl = $data['authorization_url'] ?? null;
            if (! is_string($authUrl) || $authUrl === '') {
                $attempt = PaymentAttempt::where('reference', $reference)->first();
                $attempt?->markFailed('Paystack did not return a checkout URL.');
                throw new RuntimeException('Paystack did not return a checkout URL.');
            }

            session([
                'billing.checkout_reference' => $reference,
                'billing.plan_id' => $plan->id,
                'billing.organization_id' => $organization->id,
                'billing.invoice_id' => $invoice->id,
            ]);

            return redirect()->away($authUrl);
        } catch (Throwable $e) {
            Log::warning('Paystack checkout failed', ['error' => $e->getMessage()]);

            if (isset($reference)) {
                $attempt = PaymentAttempt::where('reference', $reference)->first();
                $attempt?->markFailed($e->getMessage());
            }

            return back()->with('error', __('Could not start Paystack checkout. Try again or continue on trial.'));
        }
    }

    public function callback(Request $request, PaystackPaymentConfirmationService $confirmation): RedirectResponse
    {
        $reference = (string) $request->query('reference', session('billing.checkout_reference', ''));
        $organization = $this->ownedOrganization($request);
        abort_unless($organization !== null, 403);

        if ($reference === '') {
            $confirmation->confirmPending($organization, immediate: true);
            $organization->load('subscription.plan');

            if ($organization->fresh()?->needsUpgradeCta() === false) {
                return redirect()->route('creator.home')
                    ->with('status', __('Subscription activated. You’re ready to broadcast.'));
            }

            return redirect()->route('billing.plans')
                ->with('error', __('Missing payment reference. If you were charged, it will activate shortly.'));
        }

        $ok = $confirmation->confirmReference($reference);
        session()->forget(['billing.checkout_reference', 'billing.plan_id', 'billing.organization_id']);

        if (! $ok) {
            return redirect()->route('billing.plans')
                ->with('error', __('Payment could not be verified yet. If you were charged, we will confirm it with Paystack shortly.'));
        }

        return redirect()->route('creator.home')
            ->with('status', __('Subscription activated. You’re ready to broadcast.'));
    }

    protected function activateFreePlan(
        Organization $organization,
        Plan $plan,
        FreePeriodService $freePeriod,
    ): RedirectResponse {
        $subscription = $organization->subscription;
        $endsAt = $freePeriod->windowEndsAt($organization, $subscription);

        if ($subscription?->isOnFreePeriod() && $subscription->trial_ends_at?->isPast()) {
            return back()->with('error', __('Your free period has ended. Subscribe to keep broadcasting.'));
        }

        if ($endsAt->isPast() && ($subscription === null || $subscription->isOnFreePeriod())) {
            return back()->with('error', __('Your free period has ended. Subscribe to keep broadcasting.'));
        }

        if ($subscription) {
            $payload = [
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'ends_at' => null,
                'renews_at' => null,
            ];
            if ($subscription->trial_ends_at === null) {
                $payload['trial_ends_at'] = $endsAt;
            }
            $subscription->update($payload);
        } else {
            Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => $endsAt,
            ]);
        }

        $days = $organization->fresh()?->subscription?->freeDaysRemaining();

        return redirect()->route('creator.home')
            ->with('status', $days !== null
                ? __('You’re on Free — :days day(s) left to go live. Upgrade anytime from billing.', ['days' => $days])
                : __('You’re on the Free plan. Upgrade anytime from billing.'));
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
            'renews_at' => $plan?->periodEndFrom(now()),
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
                'renews_at' => $plan?->periodEndFrom(now()),
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
