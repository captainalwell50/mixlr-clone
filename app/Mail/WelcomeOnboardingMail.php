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

class WelcomeOnboardingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public User $user,
        public ?string $channelHandle = null,
        public ?string $organizationName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address', 'hello@soundmix.live'), config('app.name', 'SoundMix Live')),
            replyTo: [new Address('hello@soundmix.live', 'SoundMix Team')],
            subject: 'Welcome to SoundMix Live!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            text: 'emails.welcome-text',
            with: [
                'user' => $this->user,
                'channelHandle' => $this->channelHandle,
                'organizationName' => $this->organizationName,
                'ctaUrl' => route('onboarding.show'),
                'supportEmail' => 'hello@soundmix.live',
                'replyTo' => 'hello@soundmix.live',
            ],
        );
    }
}
