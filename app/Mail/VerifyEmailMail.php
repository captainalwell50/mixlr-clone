<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public User $user,
        public string $verificationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@soundmix.live', 'SoundMix Verification'),
            replyTo: [new Address('support@soundmix.live', 'SoundMix Support')],
            subject: 'Verify your email address - SoundMix Live',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
            text: 'emails.verify-email-text',
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
                'replyTo' => 'support@soundmix.live',
            ],
        );
    }
}
