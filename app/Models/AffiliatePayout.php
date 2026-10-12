<?php

namespace App\Models;

use App\Enums\AffiliatePayoutStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AffiliatePayout extends Model
{
    protected $fillable = [
        'affiliate_account_id',
        'payout_reference',
        'currency',
        'amount_minor',
        'status',
        'notes',
        'admin_id',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliatePayoutStatus::class,
            'amount_minor' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AffiliatePayoutItem::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AffiliateCommissionAudit::class);
    }

    public function canBeApproved(): bool
    {
        return $this->status->canBeApproved();
    }

    public function canBePaid(): bool
    {
        return $this->status->canBePaid();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function formattedAmount(): string
    {
        $major = $this->amount_minor / 100;

        return $this->currency.' '.number_format($major, 2);
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'apo_'.strtolower(Str::random(12));
        } while (static::query()->where('payout_reference', $ref)->exists());

        return $ref;
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', AffiliatePayoutStatus::Draft->value);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', AffiliatePayoutStatus::Approved->value);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', AffiliatePayoutStatus::Paid->value);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', AffiliatePayoutStatus::Cancelled->value);
    }
}
