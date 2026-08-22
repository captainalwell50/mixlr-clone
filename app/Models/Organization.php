<?php

namespace App\Models;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

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
        'giving_enabled',
        'giving_url',
        'giving_account_name',
        'giving_bank_name',
        'giving_account_number',
        'giving_note',
        'paystack_customer_code',
        'is_public',
        'branding_config',
    ];

    protected function casts(): array
    {
        return [
            'branding_config' => 'array',
            'is_public' => 'boolean',
            'giving_enabled' => 'boolean',
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

        if (! $subscription->allowsBroadcast()) {
            return false;
        }

        return $this->hasFeature('live_streaming');
    }

    public function plan(): ?Plan
    {
        $this->loadMissing('subscription.plan');

        return $this->subscription?->plan;
    }

    public function hasFeature(string $feature): bool
    {
        $plan = $this->plan();
        if ($plan === null) {
            // No plan row yet — allow core features so onboarding is not blocked.
            return (bool) data_get(config('plan_features.features'), $feature.'.default', false)
                || in_array($feature, ['live_streaming', 'channel_page', 'followable_profile', 'gallery'], true);
        }

        return $plan->hasFeature($feature);
    }

    public function planLimit(string $key, mixed $default = null): mixed
    {
        $plan = $this->plan();
        if ($plan === null) {
            return $default;
        }

        return $plan->limit($key, $default);
    }

    public function assertFeature(string $feature, ?string $message = null): void
    {
        if ($this->hasFeature($feature)) {
            return;
        }

        abort(402, $message ?: 'Upgrade your plan to use this feature.');
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

    public function driveConnection(): HasOne
    {
        return $this->hasOne(OrganizationDriveConnection::class);
    }

    public function studioAudioAssets(): HasMany
    {
        return $this->hasMany(StudioAudioAsset::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Public Mixlr-style channel URL (subdomain when enabled, else /c/{slug}).
     */
    public function channelUrl(): string
    {
        if (config('app.channel_subdomains') && filled(config('app.channel_domain'))) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return $scheme.'://'.$this->slug.'.'.config('app.channel_domain');
        }

        return route('channels.show', $this);
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
        return $this->resolvePublicAssetUrl($this->artwork_path);
    }

    public function logoUrl(): ?string
    {
        return $this->resolvePublicAssetUrl($this->logo_path);
    }

    /**
     * Listeners may give when the operator enabled it and set a URL and/or account.
     */
    public function givingIsPublic(): bool
    {
        return (bool) $this->giving_enabled && $this->hasGivingDestination();
    }

    public function givingUrl(): ?string
    {
        foreach ([$this->giving_url, $this->support_url] as $url) {
            if (is_string($url) && $url !== '' && preg_match('#^https?://#i', $url)) {
                return $url;
            }
        }

        return null;
    }

    public function hasGivingAccount(): bool
    {
        return filled($this->giving_account_name)
            || filled($this->giving_bank_name)
            || filled($this->giving_account_number);
    }

    public function hasGivingDestination(): bool
    {
        return filled($this->givingUrl()) || $this->hasGivingAccount();
    }

    /**
     * Public listen payload. Null when the Give online button should stay hidden.
     *
     * @return array{enabled: bool, url: ?string, account_name: ?string, bank_name: ?string, account_number: ?string, note: ?string}|null
     */
    public function publicGiving(): ?array
    {
        if (! $this->givingIsPublic()) {
            return null;
        }

        return [
            'enabled' => true,
            'url' => $this->givingUrl(),
            'account_name' => $this->giving_account_name ?: null,
            'bank_name' => $this->giving_bank_name ?: null,
            'account_number' => $this->giving_account_number ?: null,
            'note' => $this->giving_note ?: null,
        ];
    }

    private function resolvePublicAssetUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
