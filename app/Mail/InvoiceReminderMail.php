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

class InvoiceReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Invoice $invoice,
        public string $stage,
    ) {
        $this->invoice->loadMissing('organization');
    }

    public function envelope(): Envelope
    {
        $prefix = str_contains($this->stage, 'overdue') ? 'OVERDUE: ' : 'Reminder: ';
        $subject = $prefix.'Invoice '.$this->invoice->invoice_number.' - SoundMix Live';

        return new Envelope(
            from: new Address('billing@soundmix.live', 'SoundMix Billing'),
            replyTo: [new Address('billing@soundmix.live', 'SoundMix Billing')],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $stageText = match ($this->stage) {
            '7_days_before' => 'Your invoice is due in 7 days.',
            '3_days_before' => 'Your invoice is due in 3 days.',
            'due_date' => 'Your invoice is due today.',
            '3_days_overdue' => 'Your invoice is now 3 days overdue. Please renew to avoid broadcast interruption.',
            '7_days_overdue' => 'Your invoice is 7 days overdue. Broadcast privileges may be suspended soon.',
            default => 'This is a reminder regarding your pending invoice.',
        };

        return new Content(
            view: 'emails.invoice-reminder',
            text: 'emails.invoice-reminder-text',
            with: [
                'invoice' => $this->invoice,
                'organization' => $this->invoice->organization,
                'stage' => $this->stage,
                'stageText' => $stageText,
                'amountFormatted' => number_format((float) ($this->invoice->amount / 100), 2),
                'dueDateFormatted' => \App\Support\LocalTime::format($this->invoice->due_date, 'F j, Y'),
                'paymentLink' => $this->invoice->payment_link ?? route('billing.plans'),
                'billingEmail' => 'billing@soundmix.live',
                'replyTo' => 'billing@soundmix.live',
            ],
        );
    }
}
