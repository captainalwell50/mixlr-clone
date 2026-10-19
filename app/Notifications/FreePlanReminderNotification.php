<?php

namespace App\Notifications;

use App\Enums\EmailCategory;
use App\Models\EmailDelivery;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreePlanReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Organization $organization,
        public Subscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysLeft = $this->subscription->freeDaysRemaining() ?? 0;
        $ends = $this->subscription->trial_ends_at;
        $endsAtFormatted = $ends ? \App\Support\LocalTime::format($ends, 'F j, Y') : 'soon';
        $upgradeUrl = route('billing.plans');
        $notifiableId = $notifiable instanceof User ? $notifiable->id : ($notifiable->id ?? 0);
        $recipient = $notifiable->email ?? '';

        $eventKey = "free-plan-reminder:{$this->subscription->id}:{$notifiableId}";
        try {
            EmailDelivery::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'user_id' => $notifiable instanceof User ? $notifiable->id : null,
                    'recipient' => $recipient,
                    'type' => 'free_plan_reminder',
                    'category' => EmailCategory::Operational,
                    'status' => EmailDelivery::STATUS_SENT,
                    'sent_at' => now(),
                    'attempts' => 1,
                    'metadata' => [
                        'organization_id' => $this->organization->id,
                        'subscription_id' => $this->subscription->id,
                        'days_left' => $daysLeft,
                    ],
                ]
            );
        } catch (\Throwable) {
        }

        return (new MailMessage)
            ->subject('Your Free access ends soon')
            ->view('emails.free-plan-reminder', [
                'organization' => $this->organization,
                'subscription' => $this->subscription,
                'notifiable' => $notifiable,
                'daysLeft' => $daysLeft,
                'endsAtFormatted' => $endsAtFormatted,
                'upgradeUrl' => $upgradeUrl,
            ])
            ->text('emails.free-plan-reminder-text', [
                'organization' => $this->organization,
                'subscription' => $this->subscription,
                'notifiable' => $notifiable,
                'daysLeft' => $daysLeft,
                'endsAtFormatted' => $endsAtFormatted,
                'upgradeUrl' => $upgradeUrl,
            ]);
    }
}
