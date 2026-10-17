<?php

namespace App\Mail;

use App\Models\AffiliateCommission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliateCommissionReversedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public AffiliateCommission $commission,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('partners@soundmix.live', 'SoundMix Affiliate Programme'),
            replyTo: [new Address('partners@soundmix.live', 'SoundMix Affiliate Programme')],
            subject: 'Notice of Commission Reversal - SoundMix Partners',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.affiliate-commission-reversed',
            with: [
                'commission' => $this->commission,
                'affiliate' => $this->commission->affiliateAccount,
                'user' => $this->commission->affiliateAccount->user,
                'reason' => $this->reason,
                'statementUrl' => route('affiliate.commissions.index'),
            ],
        );
    }
}
