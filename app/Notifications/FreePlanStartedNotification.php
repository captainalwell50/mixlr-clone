<?php

namespace App\Notifications;

use App\Enums\EmailCategory;
use App\Models\EmailDelivery;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreePlanStartedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public Organization $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) config('billing.free_days', 30);
        $this->organization->loadMissing('subscription');
        $ends = $this->organization->subscription?->trial_ends_at;
        $endsAtFormatted = $ends ? \App\Support\LocalTime::format($ends, 'F j, Y') : null;
        $upgradeUrl = route('billing.plans');
        $notifiableId = $notifiable instanceof User ? $notifiable->id : ($notifiable->id ?? 0);
        $recipient = $notifiable->email ?? '';

        $eventKey = "free-plan-started:{$this->organization->id}:{$notifiableId}";
        try {
            EmailDelivery::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'user_id' => $notifiable instanceof User ? $notifiable->id : null,
                    'recipient' => $recipient,
                    'type' => 'free_plan_started',
                    'category' => EmailCategory::Operational,
                    'status' => EmailDelivery::STATUS_SENT,
                    'sent_at' => now(),
                    'attempts' => 1,
                    'metadata' => [
                        'organization_id' => $this->organization->id,
                        'days' => $days,
                    ],
                ]
            );
        } catch (\Throwable) {
        }

        return (new MailMessage)
            ->subject('Your '.$days.'-day Free access has started')
            ->view('emails.free-plan-started', [
                'organization' => $this->organization,
                'notifiable' => $notifiable,
                'days' => $days,
                'endsAtFormatted' => $endsAtFormatted,
                'upgradeUrl' => $upgradeUrl,
                'supportEmail' => 'support@soundmix.live',
            ])
            ->text('emails.free-plan-started-text', [
                'organization' => $this->organization,
                'notifiable' => $notifiable,
                'days' => $days,
                'endsAtFormatted' => $endsAtFormatted,
                'upgradeUrl' => $upgradeUrl,
                'supportEmail' => 'support@soundmix.live',
            ]);
    }
}
