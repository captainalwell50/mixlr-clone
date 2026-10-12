<?php

namespace App\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Canonical domain event emitted when a subscription payment is reversed, refunded, or disputed.
 * Decoupled from provider-specific gateways.
 */
class SubscriptionPaymentReversed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $paymentReference,
        public readonly ?string $reversalReference = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $reason = null,
        public readonly ?DateTimeInterface $reversedAt = null,
        public readonly ?int $actorId = null,
        public readonly array $metadata = [],
    ) {}
}
