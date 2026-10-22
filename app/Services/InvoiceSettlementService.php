<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;
use App\Events\InvoicePartiallyPaid;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvoiceSettlementService
{
    /**
     * Settle a confirmed payment against a target invoice atomically and idempotently.
     */
    public function settlePayment(Payment $payment, Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($payment, $invoice) {
            // Lock and reload fresh records within transaction
            $invoice = Invoice::query()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->where('id', $payment->id)->lockForUpdate()->firstOrFail();

            // Guard: Cannot settle void or cancelled invoices
            if (in_array($invoice->statusEnum, [InvoiceStatus::Void, InvoiceStatus::Cancelled], true)
                || in_array($invoice->status, ['void', 'cancelled'], true)) {
                throw new DomainException("Cannot settle {$invoice->status} invoice #{$invoice->invoice_number}.");
            }

            // Guard: Currency match
            if (strtoupper($payment->currency) !== strtoupper($invoice->currency)) {
                throw new DomainException("Currency mismatch: payment currency is {$payment->currency}, invoice currency is {$invoice->currency}.");
            }

            // Idempotency: check if an allocation already exists for this payment and invoice
            $existingAllocation = PaymentAllocation::query()
                ->where('payment_id', $payment->id)
                ->where('invoice_id', $invoice->id)
                ->first();

            if ($existingAllocation === null) {
                // Compute available unallocated funds on payment
                $alreadyAllocatedFromPayment = (int) PaymentAllocation::query()
                    ->where('payment_id', $payment->id)
                    ->sum('amount');
                $availableFromPayment = max(0, $payment->amount - $alreadyAllocatedFromPayment);

                // Compute outstanding due balance on invoice
                $alreadyPaidOnInvoice = (int) PaymentAllocation::query()
                    ->where('invoice_id', $invoice->id)
                    ->sum('amount');
                $outstandingDue = max(0, $invoice->total - $alreadyPaidOnInvoice);

                // Overpayment rule: allocate up to outstanding due and available funds
                $allocationAmount = min($outstandingDue, $availableFromPayment);

                if ($allocationAmount > 0) {
                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'payment_attempt_id' => $payment->payment_attempt_id,
                        'invoice_id' => $invoice->id,
                        'amount' => $allocationAmount,
                        'allocated_at' => now(),
                        'notes' => "Settlement from payment reference {$payment->reference}",
                    ]);
                }
            }

            return $this->recomputeAndApplySettlement($invoice, $payment);
        });
    }

    /**
     * Recompute derived balance and status strictly from confirmed allocations.
     * Preserves issued financial facts (invoice_number, totals, dates, plan, org).
     */
    public function recomputeAndApplySettlement(Invoice $invoice, ?Payment $triggeringPayment = null): Invoice
    {
        $totalAllocated = (int) PaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount');

        $previousStatus = $invoice->statusEnum;
        $wasPaid = $previousStatus === InvoiceStatus::Paid;

        $newAmountPaid = max(0, $totalAllocated);
        $newAmountDue = max(0, $invoice->total - $newAmountPaid);

        $invoice->amount_paid = $newAmountPaid;
        $invoice->amount_due = $newAmountDue;

        $shouldFirePaid = false;
        $shouldFirePartiallyPaid = false;

        if ($newAmountPaid >= $invoice->total && $invoice->total > 0) {
            $invoice->status = InvoiceStatus::Paid->value;
            if ($invoice->paid_at === null) {
                $invoice->paid_at = now();
            }
            if (! $wasPaid) {
                $shouldFirePaid = true;
            }
        } elseif ($newAmountPaid > 0) {
            $invoice->status = InvoiceStatus::PartiallyPaid->value;
            if ($previousStatus !== InvoiceStatus::PartiallyPaid) {
                $shouldFirePartiallyPaid = true;
            }
        } else {
            // No allocations: preserve open / current state
            if ($previousStatus === InvoiceStatus::PartiallyPaid) {
                $invoice->status = InvoiceStatus::Open->value;
            }
        }

        // Save only mutable settlement fields
        $invoice->save();

        if ($shouldFirePaid) {
            InvoicePaid::dispatch($invoice, $triggeringPayment);
        } elseif ($shouldFirePartiallyPaid) {
            InvoicePartiallyPaid::dispatch($invoice, $triggeringPayment);
        }

        return $invoice;
    }

    /**
     * Standalone recompute helper.
     */
    public function recompute(Invoice $invoice): Invoice
    {
        return $this->recomputeAndApplySettlement($invoice);
    }
}
