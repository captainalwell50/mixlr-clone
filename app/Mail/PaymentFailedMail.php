<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Organization $organization,
        public ?Plan $plan = null,
        public ?string $safeReason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('billing@soundmix.live', 'SoundMix Billing'),
            replyTo: [new Address('billing@soundmix.live', 'SoundMix Billing')],
            subject: 'Action Required: Payment attempt unsuccessful - SoundMix Live',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed',
            text: 'emails.payment-failed-text',
            with: [
                'organization' => $this->organization,
                'plan' => $this->plan,
                'safeReason' => $this->safeReason ?? 'The automated card charge could not be completed by your financial institution.',
                'retryUrl' => route('billing.plans'),
                'billingEmail' => 'billing@soundmix.live',
                'replyTo' => 'billing@soundmix.live',
            ],
        );
    }
}
