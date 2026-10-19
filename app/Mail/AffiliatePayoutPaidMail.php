<?php

namespace App\Mail;

use App\Models\AffiliatePayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliatePayoutPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public AffiliatePayout $payout,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('partners@soundmix.live', 'SoundMix Affiliate Programme'),
            replyTo: [new Address('partners@soundmix.live', 'SoundMix Affiliate Programme')],
            subject: 'Payout Disbursed: '.$this->payout->formattedAmount().' - SoundMix Partners',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.affiliate-payout-paid',
            text: 'emails.affiliate-payout-paid-text',
            with: [
                'payout' => $this->payout,
                'affiliate' => $this->payout->affiliateAccount,
                'user' => $this->payout->affiliateAccount->user,
                'payoutsUrl' => route('affiliate.payouts.index'),
            ],
        );
    }
}
