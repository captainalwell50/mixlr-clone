<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionAssignmentService
{
    public function __construct(private FreePeriodService $freePeriod) {}

    /**
     * Operator or checkout helper: put the channel on this package.
     *
     * Paid plans are unlimited. Free gets a fresh 30-day window when
     * $resetFreeWindow is true (admin comps); otherwise the original signup clock is kept.
     */
    public function assign(Organization $organization, Plan $plan, bool $resetFreeWindow = false): Subscription
    {
        if ($plan->isFree()) {
            return $this->assignFree($organization, $plan, $resetFreeWindow);
        }

        return $this->assignPaid($organization, $plan);
    }

    public function assignPaid(Organization $organization, Plan $plan, array $extra = []): Subscription
    {
        $payload = [
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'ends_at' => null,
            'renews_at' => $plan->periodEndFrom(now()),
            'free_reminder_sent_at' => null,
            'free_expired_notified_at' => null,
        ];

        foreach ($extra as $key => $value) {
            if ($value !== null && $value !== '') {
                $payload[$key] = $value;
            }
        }

        $sub = $this->upsert($organization, $payload);

        try {
            $owners = $organization->users()
                ->wherePivot('role', \App\Enums\OrgRole::Owner->value)
                ->get();
            if ($owners->isEmpty()) {
                $owners = $organization->users;
            }

            $outbound = app(OutboundEmailService::class);
            foreach ($owners as $user) {
                $outbound->sendOperational(
                    mailable: new \App\Mail\SubscriptionActivatedMail($organization, $plan),
                    recipient: $user->email,
                    type: 'subscription_activated',
                    eventKey: "subscription-activated:{$organization->id}:{$plan->id}:{$user->id}",
                    user: $user,
                    metadata: [
                        'organization_id' => $organization->id,
                        'plan_id' => $plan->id,
                    ]
                );
            }
        } catch (\Throwable) {
        }

        return $sub;
    }

    public function assignFree(Organization $organization, Plan $plan, bool $resetWindow): Subscription
    {
        $subscription = $organization->subscription;
        $endsAt = $resetWindow
            ? $this->freePeriod->endsAtForNewSignup()
            : $this->freePeriod->windowEndsAt($organization, $subscription);

        if (! $resetWindow && $subscription?->trial_ends_at) {
            $endsAt = $subscription->trial_ends_at;
        }

        return $this->upsert($organization, [
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => $endsAt,
            'ends_at' => null,
            'renews_at' => null,
            'free_reminder_sent_at' => $resetWindow ? null : $subscription?->free_reminder_sent_at,
            'free_expired_notified_at' => $resetWindow ? null : $subscription?->free_expired_notified_at,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsert(Organization $organization, array $payload): Subscription
    {
        $subscription = $organization->subscription;
        if ($subscription) {
            $subscription->update($payload);

            return $subscription->fresh(['plan']) ?? $subscription;
        }

        return Subscription::query()->create([
            'organization_id' => $organization->id,
            ...$payload,
        ]);
    }
}
