<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentAttempt extends Model
{
    use HasFactory;

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'invoice_id',
        'organization_id',
        'reference',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'idempotency_key',
        'failure_reason',
        'provider_payload',
        'initiated_at',
        'confirmed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'provider_payload' => 'array',
            'initiated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(PaymentAllocation::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_INITIATED, self::STATUS_PENDING], true);
    }

    public function markSuccessful(?string $providerReference = null, ?array $payload = null): self
    {
        $this->update([
            'status' => self::STATUS_SUCCESSFUL,
            'provider_reference' => $providerReference ?? $this->provider_reference,
            'provider_payload' => $payload ?? $this->provider_payload,
            'confirmed_at' => now(),
            'failure_reason' => null,
        ]);

        return $this;
    }

    public function markFailed(?string $reason = null, ?array $payload = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason ?? $this->failure_reason,
            'provider_payload' => $payload ?? $this->provider_payload,
            'failed_at' => now(),
        ]);

        return $this;
    }

    public function markAbandoned(): self
    {
        $this->update([
            'status' => self::STATUS_ABANDONED,
        ]);

        return $this;
    }
}
