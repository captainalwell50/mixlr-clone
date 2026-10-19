<?php

namespace App\Services;

use App\Enums\EmailCategory;
use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OutboundEmailService
{
    /**
     * Send or queue a transactional email.
     */
    public function sendTransactional(
        Mailable $mailable,
        string $recipient,
        string $type,
        ?string $eventKey = null,
        ?User $user = null,
        array $metadata = []
    ): ?EmailDelivery {
        return $this->send(
            mailable: $mailable,
            recipient: $recipient,
            type: $type,
            category: EmailCategory::Transactional,
            eventKey: $eventKey,
            user: $user,
            metadata: $metadata
        );
    }

    /**
     * Send or queue an operational email (e.g. billing alerts, security).
     */
    public function sendOperational(
        Mailable $mailable,
        string $recipient,
        string $type,
        ?string $eventKey = null,
        ?User $user = null,
        array $metadata = []
    ): ?EmailDelivery {
        return $this->send(
            mailable: $mailable,
            recipient: $recipient,
            type: $type,
            category: EmailCategory::Operational,
            eventKey: $eventKey,
            user: $user,
            metadata: $metadata
        );
    }

    /**
     * Send or queue a marketing email. Suppressed if user is not opted in or unsubscribed.
     */
    public function sendMarketing(
        Mailable $mailable,
        User $user,
        string $type,
        ?string $eventKey = null,
        array $metadata = []
    ): ?EmailDelivery {
        if (! $user->allowsMarketingEmail()) {
            Log::info('Marketing email suppressed due to user preferences', [
                'user_id' => $user->id,
                'email' => $user->email,
                'type' => $type,
            ]);

            if ($eventKey) {
                EmailDelivery::query()->firstOrCreate(
                    ['event_key' => $eventKey],
                    [
                        'user_id' => $user->id,
                        'recipient' => $user->email,
                        'type' => $type,
                        'category' => EmailCategory::Marketing,
                        'status' => 'suppressed',
                        'last_error' => 'User opted out of marketing communications',
                    ]
                );
            }

            return null;
        }

        return $this->send(
            mailable: $mailable,
            recipient: $user->email,
            type: $type,
            category: EmailCategory::Marketing,
            eventKey: $eventKey,
            user: $user,
            metadata: $metadata
        );
    }

    /**
     * Base dispatch mechanism with idempotency check, audit tracking and safe execution.
     */
    public function send(
        Mailable $mailable,
        string $recipient,
        string $type,
        EmailCategory $category,
        ?string $eventKey = null,
        ?User $user = null,
        array $metadata = []
    ): ?EmailDelivery {
        // 1. Idempotency check on event_key
        if ($eventKey !== null && $eventKey !== '') {
            $existing = EmailDelivery::query()->where('event_key', $eventKey)->first();
            if ($existing) {
                if (in_array($existing->status, [EmailDelivery::STATUS_SENT, EmailDelivery::STATUS_DELIVERED, 'suppressed'], true)) {
                    Log::info('Email delivery skipped: idempotency key already satisfied', [
                        'event_key' => $eventKey,
                        'status' => $existing->status,
                    ]);

                    return $existing;
                }
            }
        }

        // 2. Create or acquire ledger record
        $delivery = EmailDelivery::query()->updateOrCreate(
            $eventKey ? ['event_key' => $eventKey] : ['id' => 0], // id=0 forces create when eventKey is null
            [
                'user_id' => $user?->id,
                'recipient' => $recipient,
                'type' => $type,
                'category' => $category,
                'event_key' => $eventKey,
                'status' => EmailDelivery::STATUS_PENDING,
                'metadata' => $this->sanitizeMetadata($metadata),
            ]
        );

        // 3. Dispatch via Laravel Mail
        try {
            if ($mailable instanceof \Illuminate\Contracts\Queue\ShouldQueue) {
                Mail::to($recipient)->queue($mailable);
            } else {
                Mail::to($recipient)->send($mailable);
            }

            $delivery->markSent();
        } catch (Throwable $e) {
            $delivery->markFailed($e->getMessage());
            Log::error('Failed sending outbound email', [
                'type' => $type,
                'recipient' => $recipient,
                'error' => EmailDelivery::sanitizeErrorMessage($e->getMessage()),
            ]);
        }

        return $delivery;
    }

    /**
     * Ensure no credentials, tokens, or card numbers are persisted in metadata.
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];
        foreach ($metadata as $key => $val) {
            if (is_string($val)) {
                if (preg_match('/(password|token|secret|key|cvv|authorization|bearer)/i', $key)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = EmailDelivery::sanitizeErrorMessage($val);
                }
            } elseif (is_array($val)) {
                $sanitized[$key] = $this->sanitizeMetadata($val);
            } else {
                $sanitized[$key] = $val;
            }
        }

        return $sanitized;
    }
}
