<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'paystack_plan_code',
        'amount',
        'currency',
        'interval',
        'limits',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'is_active' => 'boolean',
            'amount' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->amount === 0 || $this->slug === 'free';
    }

    public function amountLabel(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        $major = $this->amount / 100;

        return $this->currency.' '.number_format($major, 0);
    }

    public function maxStreams(): int
    {
        return (int) data_get($this->limits, 'max_streams', 1);
    }
}
