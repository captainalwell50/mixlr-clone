<?php

namespace App\Mail;

use App\Models\DigitalLibraryPurchase;
use App\Models\DigitalLibrarySeries;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DigitalLibraryPurchaseReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public User $user,
        public DigitalLibrarySeries $series,
        public int|float|string $amount,
        public string $currency,
        public string $providerReference,
        public ?CarbonInterface $paymentDate = null,
    ) {
        $this->paymentDate = $paymentDate ?? now();
    }

    public static function fromPurchase(DigitalLibraryPurchase $purchase): self
    {
        $purchase->loadMissing(['user', 'series']);

        return new self(
            user: $purchase->user,
            series: $purchase->series,
            amount: $purchase->amount_collected_minor / 100,
            currency: $purchase->currency,
            providerReference: $purchase->provider_reference,
            paymentDate: $purchase->paid_at ?? now(),
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('billing@soundmix.live', 'SoundMix Billing'),
            replyTo: [new Address('support@soundmix.live', 'SoundMix Support')],
            subject: 'Receipt: '.$this->series->title.' - SoundMix Digital Library',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digital-library-receipt',
            text: 'emails.digital-library-receipt-text',
            with: [
                'user' => $this->user,
                'series' => $this->series,
                'amountFormatted' => number_format((float) $this->amount, 2),
                'currency' => strtoupper($this->currency),
                'providerReference' => $this->providerReference,
                'paymentDateFormatted' => \App\Support\LocalTime::format($this->paymentDate, 'F j, Y, g:i A T'),
                'seriesUrl' => route('digital-library.series.public', $this->series),
                'supportEmail' => 'support@soundmix.live',
                'replyTo' => 'support@soundmix.live',
            ],
        );
    }
}
