<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function allowsBroadcast(): bool
    {
        if ($this->status === SubscriptionStatus::Trialing) {
            return $this->trial_ends_at === null || $this->trial_ends_at->isFuture();
        }

        return $this->status->allowsBroadcast();
    }
}
