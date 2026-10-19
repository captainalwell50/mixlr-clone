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

class SubscriptionActivatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Organization $organization,
        public Plan $plan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('billing@soundmix.live', 'SoundMix Billing'),
            replyTo: [new Address('billing@soundmix.live', 'SoundMix Billing')],
            subject: 'Subscription Activated: '.$this->plan->name.' Plan - SoundMix Live',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-activated',
            text: 'emails.subscription-activated-text',
            with: [
                'organization' => $this->organization,
                'plan' => $this->plan,
                'dashboardUrl' => route('dashboard'),
                'billingEmail' => 'billing@soundmix.live',
                'replyTo' => 'billing@soundmix.live',
            ],
        );
    }
}
