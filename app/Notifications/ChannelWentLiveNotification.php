<?php

namespace App\Notifications;

use App\Enums\EmailCategory;
use App\Models\EmailDelivery;
use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChannelWentLiveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public Event $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $channel = $this->event->organization;
        $listenUrl = route('events.show', $this->event);
        $notifiableId = $notifiable instanceof User ? $notifiable->id : ($notifiable->id ?? 0);
        $recipient = $notifiable->email ?? '';

        // Ledger logging and idempotency record
        $eventKey = "channel-went-live:{$this->event->id}:{$notifiableId}";
        try {
            EmailDelivery::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'user_id' => $notifiable instanceof User ? $notifiable->id : null,
                    'recipient' => $recipient,
                    'type' => 'channel_went_live',
                    'category' => EmailCategory::Operational,
                    'status' => EmailDelivery::STATUS_SENT,
                    'sent_at' => now(),
                    'attempts' => 1,
                    'metadata' => [
                        'event_id' => $this->event->id,
                        'organization_id' => $this->event->organization_id,
                    ],
                ]
            );
        } catch (\Throwable) {
        }

        return (new MailMessage)
            ->subject($channel->name.' is live: '.$this->event->title)
            ->view('emails.channel-went-live', [
                'event' => $this->event,
                'channel' => $channel,
                'notifiable' => $notifiable,
                'listenUrl' => $listenUrl,
            ])
            ->text('emails.channel-went-live-text', [
                'event' => $this->event,
                'channel' => $channel,
                'notifiable' => $notifiable,
                'listenUrl' => $listenUrl,
            ]);
    }
}
