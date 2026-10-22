<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event emitted when a payment attempt succeeds and produces a confirmed Payment.
 */
class PaymentAttemptSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentAttempt $paymentAttempt,
        public readonly ?Payment $payment = null,
    ) {}
}
