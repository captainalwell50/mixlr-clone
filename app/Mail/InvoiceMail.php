<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Invoice $invoice,
    ) {
        $this->invoice->loadMissing('organization');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('billing@soundmix.live', 'SoundMix Billing'),
            replyTo: [new Address('billing@soundmix.live', 'SoundMix Billing')],
            subject: 'Invoice '.$this->invoice->invoice_number.' from SoundMix Live',
        );
    }

    public function content(): Content
    {
        $period = null;
        if ($this->invoice->billing_period_start && $this->invoice->billing_period_end) {
            $period = \App\Support\LocalTime::format($this->invoice->billing_period_start, 'M j, Y')
                .' — '
                .\App\Support\LocalTime::format($this->invoice->billing_period_end, 'M j, Y');
        }

        return new Content(
            view: 'emails.invoice',
            text: 'emails.invoice-text',
            with: [
                'invoice' => $this->invoice,
                'organization' => $this->invoice->organization,
                'billingPeriod' => $period,
                'amountFormatted' => number_format((float) ($this->invoice->amount / 100), 2),
                'dueDateFormatted' => \App\Support\LocalTime::format($this->invoice->due_date, 'F j, Y'),
                'paymentLink' => $this->invoice->payment_link ?? route('billing.plans'),
                'billingEmail' => 'billing@soundmix.live',
                'replyTo' => 'billing@soundmix.live',
            ],
        );
    }
}
