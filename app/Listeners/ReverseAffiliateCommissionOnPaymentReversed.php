<?php

namespace App\Listeners;

use App\Events\SubscriptionPaymentReversed;
use App\Services\AffiliateCommissionService;

class ReverseAffiliateCommissionOnPaymentReversed
{
    public function __construct(
        protected AffiliateCommissionService $service,
    ) {}

    public function handle(SubscriptionPaymentReversed $event): void
    {
        $this->service->handlePaymentReversal(
            paymentReference: $event->paymentReference,
            reversalReference: $event->reversalReference,
            amountMinor: $event->amountMinor,
            reason: $event->reason ?? 'Payment reversed',
            reversedAt: $event->reversedAt,
            actorId: $event->actorId,
            metadata: $event->metadata,
        );
    }
}
