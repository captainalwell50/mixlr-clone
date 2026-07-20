<?php

namespace App\Models;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'creator_type',
        'tagline',
        'logo_path',
        'artwork_path',
        'theme_color',
        'support_url',
        'social_feed_url',
        'paystack_customer_code',
        'is_public',
        'branding_config',
    ];

    protected function casts(): array
    {
        return [
            'branding_config' => 'array',
            'is_public' => 'boolean',
            'creator_type' => CreatorType::class,
        ];
    }

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function allowsBroadcast(): bool
    {
        $subscription = $this->subscription;

        if ($subscription === null) {
            // Grandfather existing orgs without a subscription row.
            return true;
        }

        return $subscription->allowsBroadcast();
    }

    public function subscriptionStatus(): ?SubscriptionStatus
    {
        return $this->subscription?->status;
    }

    public function defaultStream(): ?Stream
    {
        return $this->streams()->orderBy('id')->first();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function follows(): HasMany
    {
        return $this->hasMany(ChannelFollow::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_follows')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function liveEvent(): ?Event
    {
        return $this->events()
            ->where('status', EventStatus::Live)
            ->latest('started_at')
            ->first();
    }

    public function themeColor(): string
    {
        return $this->theme_color
            ?: data_get($this->branding_config, 'accent', '#3d9b7a');
    }

    public function artworkUrl(): ?string
    {
        $path = $this->artwork_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }
}
