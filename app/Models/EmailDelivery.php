<?php

namespace App\Models;

use App\Enums\EmailCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDelivery extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_COMPLAINED = 'complained';

    protected $fillable = [
        'user_id',
        'recipient',
        'type',
        'category',
        'event_key',
        'provider_message_id',
        'status',
        'attempts',
        'sent_at',
        'failed_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'category' => EmailCategory::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markSent(?string $providerMessageId = null): self
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'provider_message_id' => $providerMessageId ?? $this->provider_message_id,
            'sent_at' => now(),
            'attempts' => $this->attempts + 1,
            'last_error' => null,
        ]);

        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'attempts' => $this->attempts + 1,
            'last_error' => self::sanitizeErrorMessage($error),
        ]);

        return $this;
    }

    public function markDelivered(): self
    {
        $this->update(['status' => self::STATUS_DELIVERED]);

        return $this;
    }

    public function markBounced(?string $reason = null): self
    {
        $metadata = $this->metadata ?? [];
        if ($reason !== null && $reason !== '') {
            $metadata['bounce_reason'] = self::sanitizeErrorMessage($reason);
        }

        $this->update([
            'status' => self::STATUS_BOUNCED,
            'metadata' => $metadata,
        ]);

        return $this;
    }

    public function markComplained(): self
    {
        $this->update(['status' => self::STATUS_COMPLAINED]);

        return $this;
    }

    /**
     * Strip any sensitive tokens, passwords, card numbers, or auth keys from error strings.
     */
    public static function sanitizeErrorMessage(string $error): string
    {
        $sanitized = preg_replace('/(password|token|secret|key|authorization|bearer|cvv)=([^&\s]+)/i', '$1=[REDACTED]', $error);
        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer [REDACTED]', (string) $sanitized);
        $sanitized = preg_replace('/\b\d{4}[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}\b/', '[REDACTED_CARD]', (string) $sanitized);

        return mb_substr((string) $sanitized, 0, 1000);
    }
}
