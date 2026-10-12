<?php

namespace App\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Canonical domain event emitted when a subscription payment is successfully confirmed.
 * Decoupled from provider-specific gateways (e.g. Paystack, Stripe, offline).
 */
class SubscriptionPaymentConfirmed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly ?int $planId,
        public readonly string $paymentReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly DateTimeInterface $confirmedAt,
        public readonly bool $isRenewal,
        public readonly ?int $paymentCheckoutId = null,
        public readonly ?string $providerReference = null,
        public readonly array $metadata = [],
    ) {}
}
