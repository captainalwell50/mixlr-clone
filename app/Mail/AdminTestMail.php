<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientAddress,
        public array $settingsSummary = [],
        public ?CarbonInterface $dispatchedAt = null,
    ) {
        $this->dispatchedAt = $dispatchedAt ?? now();
    }

    public function envelope(): Envelope
    {
        $fromAddress = $this->settingsSummary['from_address'] ?? config('mail.from.address', 'support@soundmix.live');
        $fromName = $this->settingsSummary['from_name'] ?? config('mail.from.name', 'SoundMix Live');
        $replyTo = $this->settingsSummary['reply_to'] ?? config('mail.reply_to.address', 'support@soundmix.live');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($replyTo, $fromName)],
            subject: '[SoundMix] Outbound SMTP Test Email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-test-mail',
            text: 'emails.admin-test-mail-text',
            with: [
                'recipientAddress' => $this->recipientAddress,
                'settingsSummary' => $this->settingsSummary,
                'dispatchedAtFormatted' => $this->dispatchedAt?->format('F j, Y, g:i A T') ?? now()->toDateTimeString(),
                'siteUrl' => config('app.url'),
            ],
        );
    }
}
