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

class FreePlanExpiredNotification extends Notification implements ShouldQueue
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
        $upgradeUrl = route('billing.plans');
        $notifiableId = $notifiable instanceof User ? $notifiable->id : ($notifiable->id ?? 0);
        $recipient = $notifiable->email ?? '';

        $eventKey = "free-plan-expired:{$this->subscription->id}:{$notifiableId}";
        try {
            EmailDelivery::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'user_id' => $notifiable instanceof User ? $notifiable->id : null,
                    'recipient' => $recipient,
                    'type' => 'free_plan_expired',
                    'category' => EmailCategory::Operational,
                    'status' => EmailDelivery::STATUS_SENT,
                    'sent_at' => now(),
                    'attempts' => 1,
                    'metadata' => [
                        'organization_id' => $this->organization->id,
                        'subscription_id' => $this->subscription->id,
                    ],
                ]
            );
        } catch (\Throwable) {
        }

        return (new MailMessage)
            ->subject('Your Free period has ended — subscribe to go live')
            ->view('emails.free-plan-expired', [
                'organization' => $this->organization,
                'subscription' => $this->subscription,
                'notifiable' => $notifiable,
                'upgradeUrl' => $upgradeUrl,
            ])
            ->text('emails.free-plan-expired-text', [
                'organization' => $this->organization,
                'subscription' => $this->subscription,
                'notifiable' => $notifiable,
                'upgradeUrl' => $upgradeUrl,
            ]);
    }
}
