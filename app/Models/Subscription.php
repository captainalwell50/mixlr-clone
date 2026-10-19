<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id',
        'plan_id',
        'status',
        'paystack_subscription_code',
        'paystack_email_token',
        'paystack_customer_code',
        'trial_ends_at',
        'ends_at',
        'renews_at',
        'free_reminder_sent_at',
        'free_expired_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'renews_at' => 'datetime',
            'free_reminder_sent_at' => 'datetime',
            'free_expired_notified_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isOnFreePeriod(): bool
    {
        $this->loadMissing('plan');

        if ($this->plan === null) {
            return true;
        }

        return $this->plan->isFree();
    }

    public function allowsBroadcast(): bool
    {
        if ($this->status === SubscriptionStatus::Trialing || $this->isOnFreePeriod()) {
            return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
        }

        return $this->status->allowsBroadcast();
    }

    public function isActive(): bool
    {
        return $this->allowsBroadcast();
    }

    public function needsUpgradeCta(): bool
    {
        return $this->isOnFreePeriod() || ! $this->allowsBroadcast();
    }

    public function freeDaysRemaining(): ?int
    {
        if (! $this->isOnFreePeriod() || $this->trial_ends_at === null) {
            return null;
        }

        if ($this->trial_ends_at->isPast()) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->copy()->startOfDay()));
    }

    public function renewalAt(): ?\Carbon\CarbonInterface
    {
        if ($this->isOnFreePeriod() || ! $this->status->allowsBroadcast()) {
            return null;
        }

        $this->loadMissing('plan');

        $next = $this->renews_at?->copy()
            ?? $this->plan?->periodEndFrom($this->updated_at ?? now())
            ?? now()->addMonth();

        $guard = 0;
        while ($next->copy()->endOfDay()->isPast() && $guard++ < 48) {
            $next = $this->plan?->periodEndFrom($next) ?? $next->copy()->addMonth();
        }

        return $next;
    }

    public function renewalDaysRemaining(): ?int
    {
        $at = $this->renewalAt();
        if ($at === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($at->copy()->startOfDay()));
    }
}
