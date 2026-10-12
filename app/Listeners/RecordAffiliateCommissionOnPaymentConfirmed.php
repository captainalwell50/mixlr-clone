<?php

namespace App\Listeners;

use App\Events\SubscriptionPaymentConfirmed;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\AffiliateCommissionService;
use Illuminate\Support\Facades\Log;

class RecordAffiliateCommissionOnPaymentConfirmed
{
    public function __construct(
        protected AffiliateCommissionService $service,
    ) {}

    public function handle(SubscriptionPaymentConfirmed $event): void
    {
        $organization = Organization::query()->find($event->organizationId);
        if ($organization === null) {
            Log::warning('SubscriptionPaymentConfirmed: organization not found', ['organization_id' => $event->organizationId]);

            return;
        }

        $plan = $event->planId ? Plan::query()->find($event->planId) : null;

        $this->service->recordConfirmedSubscriptionPayment(
            organization: $organization,
            plan: $plan,
            paymentReference: $event->paymentReference,
            grossAmountMinor: $event->amountMinor,
            currency: $event->currency,
            confirmedAt: $event->confirmedAt,
            isRenewal: $event->isRenewal,
            providerReference: $event->providerReference,
            paymentCheckoutId: $event->paymentCheckoutId,
            metadata: $event->metadata,
        );
    }
}
