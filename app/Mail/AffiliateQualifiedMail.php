<?php

namespace App\Mail;

use App\Models\AffiliateAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliateQualifiedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public AffiliateAccount $affiliateAccount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('partners@soundmix.live', 'SoundMix Affiliate Programme'),
            replyTo: [new Address('partners@soundmix.live', 'SoundMix Affiliate Programme')],
            subject: 'Congratulations! You Have Qualified for Payouts - SoundMix Partners',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.affiliate-qualified',
            text: 'emails.affiliate-qualified-text',
            with: [
                'affiliate' => $this->affiliateAccount,
                'user' => $this->affiliateAccount->user,
                'dashboardUrl' => route('affiliate.dashboard'),
            ],
        );
    }
}
