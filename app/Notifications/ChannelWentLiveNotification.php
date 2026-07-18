<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChannelWentLiveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Event $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $channel = $this->event->organization;

        return (new MailMessage)
            ->subject($channel->name.' is live: '.$this->event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($channel->name.' just went live.')
            ->line($this->event->title)
            ->action('Listen now', route('events.show', $this->event))
            ->line('You receive this because you follow this channel.');
    }
}
