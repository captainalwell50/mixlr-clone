<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event emitted when an invoice transitions to PAID.
 * Emitted only on state transition, ensuring idempotency.
 */
class InvoicePaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?Payment $payment = null,
    ) {}
}
