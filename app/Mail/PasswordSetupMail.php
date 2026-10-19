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

class PasswordSetupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public User $user,
        public string $actionUrl,
        public bool $isInitialSetup = true,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isInitialSetup ? 'Set up your SoundMix password' : 'Reset your SoundMix password';

        return new Envelope(
            from: new Address('no-reply@soundmix.live', 'SoundMix Security'),
            replyTo: [new Address('support@soundmix.live', 'SoundMix Support')],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-setup',
            text: 'emails.password-setup-text',
            with: [
                'user' => $this->user,
                'actionUrl' => $this->actionUrl,
                'isInitialSetup' => $this->isInitialSetup,
                'expiresInMinutes' => config('auth.passwords.users.expire', 60),
                'replyTo' => 'support@soundmix.live',
            ],
        );
    }
}
