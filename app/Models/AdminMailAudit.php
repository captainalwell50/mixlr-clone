<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminMailAudit extends Model
{
    public const ACTION_SETTINGS_UPDATED = 'smtp_settings_updated';
    public const ACTION_DELIVERY_ENABLED = 'smtp_delivery_enabled';
    public const ACTION_DELIVERY_DISABLED = 'smtp_delivery_disabled';
    public const ACTION_TEST_SENT = 'smtp_test_sent';
    public const ACTION_TEST_FAILED = 'smtp_test_failed';

    protected $fillable = [
        'actor_id',
        'action',
        'status',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Safely record an administrative mail audit event with all secrets redacted.
     */
    public static function record(
        string $action,
        string $status = 'success',
        array $details = [],
        ?int $actorId = null
    ): self {
        return static::create([
            'actor_id' => $actorId,
            'action' => $action,
            'status' => $status,
            'details' => static::sanitizeDetails($details),
        ]);
    }

    /**
     * Sanitize audit details so passwords, secrets, or tokens never persist.
     */
    public static function sanitizeDetails(array $details): array
    {
        $sanitized = [];

        foreach ($details as $key => $value) {
            if (preg_match('/^(.*_)?(password|secret|token|auth|credential|api_key|access_token)$/i', (string) $key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = static::sanitizeDetails($value);
            } elseif (is_string($value)) {
                // Ensure no credential-like patterns leak in strings
                $sanitized[$key] = EmailDelivery::sanitizeErrorMessage($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
