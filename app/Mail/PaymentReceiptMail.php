<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\Plan;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Organization $organization,
        public Plan $plan,
        public int|float|string $amount,
        public string $currency,
        public string $paymentReference,
        public ?CarbonInterface $paymentDate = null,
    ) {
        $this->paymentDate = $paymentDate ?? now();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('billing@soundmix.live', 'SoundMix Billing'),
            replyTo: [new Address('billing@soundmix.live', 'SoundMix Billing')],
            subject: 'Payment Receipt: '.$this->plan->name.' Plan - SoundMix Live',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-receipt',
            text: 'emails.payment-receipt-text',
            with: [
                'organization' => $this->organization,
                'plan' => $this->plan,
                'amountFormatted' => number_format((float) $this->amount, 2),
                'currency' => strtoupper($this->currency),
                'paymentReference' => $this->paymentReference,
                'paymentDateFormatted' => \App\Support\LocalTime::format($this->paymentDate, 'F j, Y, g:i A'),
                'billingEmail' => 'billing@soundmix.live',
                'replyTo' => 'billing@soundmix.live',
            ],
        );
    }
}
