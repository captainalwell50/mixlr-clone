<?php

namespace App\Models;

use App\Enums\AffiliateCommissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateCommission extends Model
{
    protected $fillable = [
        'affiliate_account_id',
        'organization_id',
        'subscription_id',
        'affiliate_payout_id',
        'payment_reference',
        'provider_reference',
        'idempotency_key',
        'gross_amount_minor',
        'commission_rate_bps',
        'commission_amount_minor',
        'is_renewal',
        'currency',
        'attribution_source',
        'status',
        'hold_until',
        'confirmed_at',
        'payable_at',
        'reversal_of_commission_id',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliateCommissionStatus::class,
            'gross_amount_minor' => 'integer',
            'commission_rate_bps' => 'integer',
            'commission_amount_minor' => 'integer',
            'is_renewal' => 'boolean',
            'hold_until' => 'datetime',
            'confirmed_at' => 'datetime',
            'payable_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_commission_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_commission_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AffiliateCommissionAudit::class);
    }

    public function isHeld(): bool
    {
        return $this->status === AffiliateCommissionStatus::Held;
    }

    public function isPayable(): bool
    {
        return $this->status === AffiliateCommissionStatus::Payable;
    }

    public function isPaid(): bool
    {
        return $this->status === AffiliateCommissionStatus::Paid;
    }

    public function isReversed(): bool
    {
        return $this->status === AffiliateCommissionStatus::Reversed;
    }

    public function isVoided(): bool
    {
        return $this->status === AffiliateCommissionStatus::Voided;
    }

    public function isAdjustment(): bool
    {
        return $this->reversal_of_commission_id !== null;
    }

    public function ratePercent(): float
    {
        return $this->commission_rate_bps / 100;
    }

    public function formattedGrossAmount(): string
    {
        $sign = $this->gross_amount_minor < 0 ? '-' : '';
        $major = abs($this->gross_amount_minor) / 100;

        return $sign.$this->currency.' '.number_format($major, 2);
    }

    public function formattedCommissionAmount(): string
    {
        $sign = $this->commission_amount_minor < 0 ? '-' : '';
        $major = abs($this->commission_amount_minor) / 100;

        return $sign.$this->currency.' '.number_format($major, 2);
    }

    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('status', AffiliateCommissionStatus::Held->value);
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->where('status', AffiliateCommissionStatus::Payable->value);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', AffiliateCommissionStatus::Paid->value);
    }

    public function scopeReversed(Builder $query): Builder
    {
        return $query->where('status', AffiliateCommissionStatus::Reversed->value);
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', AffiliateCommissionStatus::Voided->value);
    }
}
