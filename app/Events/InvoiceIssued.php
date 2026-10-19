<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
  * Canonical domain event emitted when an invoice is issued (transitions to open).
  * Decoupled from notification/email channels.
  */
class InvoiceIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
