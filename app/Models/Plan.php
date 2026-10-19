<?php

namespace App\Models;

use App\Support\PlanFeatureCatalog;
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
        'is_custom',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
            'amount' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isCustom(): bool
    {
        return (bool) $this->is_custom;
    }

    public function isFree(): bool
    {
        if ($this->isCustom()) {
            return false;
        }

        return $this->amount === 0 || $this->slug === 'free';
    }

    public function isRecommended(): bool
    {
        return strtolower((string) $this->slug) === 'pro'
            || strcasecmp((string) $this->name, 'Pro') === 0;
    }

    public function contactSalesMailto(?string $channelName = null): string
    {
        $email = (string) config('app.support_email', 'support@soundmix.live');
        $subject = filled($channelName)
            ? 'Custom package — '.$channelName
            : 'Custom package inquiry';

        return 'mailto:'.$email.'?subject='.rawurlencode($subject);
    }

    public function periodEndFrom(\DateTimeInterface $from): \Carbon\CarbonInterface
    {
        $from = \Illuminate\Support\Carbon::parse($from);

        return match (strtolower((string) $this->interval)) {
            'weekly' => $from->copy()->addWeek(),
            'quarterly' => $from->copy()->addMonths(3),
            'biannually' => $from->copy()->addMonths(6),
            'annually', 'yearly' => $from->copy()->addYear(),
            default => $from->copy()->addMonth(),
        };
    }

    public function amountLabel(): string
    {
        if ($this->isCustom()) {
            return 'Custom';
        }

        if ($this->isFree()) {
            return 'Free';
        }

        $major = $this->amount / 100;

        return $this->currency.' '.number_format($major, 0);
    }

    public function maxStreams(): int
    {
        return (int) $this->limit('max_streams', 1);
    }

    public function maxRecordings(): int
    {
        return (int) $this->limit('max_recordings', 20);
    }

    /** Platform object-storage + local library quota (bytes). Drive BYO does not count. */
    public function storageBytes(): int
    {
        return (int) $this->limit('storage_bytes', 2 * 1024 * 1024 * 1024);
    }

    public function hasFeature(string $feature): bool
    {
        $features = PlanFeatureCatalog::features();
        if (! array_key_exists($feature, $features)) {
            return false;
        }

        $default = (bool) ($features[$feature]['default'] ?? false);

        return (bool) data_get($this->limits, $feature, $default);
    }

    public function limit(string $key, mixed $default = null): mixed
    {
        return data_get($this->limits, $key, $default);
    }

    /** @return list<string> */
    public function featureBullets(): array
    {
        return PlanFeatureCatalog::marketingBullets($this->limits ?? []);
    }
}
