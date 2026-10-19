<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public User $user,
        public ?CarbonInterface $changedAt = null,
        public ?string $ipAddress = null,
    ) {
        $this->changedAt = $changedAt ?? now();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@soundmix.live', 'SoundMix Security'),
            replyTo: [new Address('support@soundmix.live', 'SoundMix Support')],
            subject: 'Security Alert: Your SoundMix password was changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
            text: 'emails.password-changed-text',
            with: [
                'user' => $this->user,
                'changedAtFormatted' => \App\Support\LocalTime::format($this->changedAt, 'F j, Y, g:i A T'),
                'ipAddress' => $this->ipAddress,
                'replyTo' => 'support@soundmix.live',
                'accountUrl' => config('app.url').'/account',
            ],
        );
    }
}
