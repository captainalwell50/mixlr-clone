<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    public const KEY_LISTEN_PREFER_HLS = 'listen_prefer_hls';

    /** @var list<string> */
    public const LISTEN_PREFER_HLS_OPTIONS = ['hls', 'whep', 'auto'];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key): ?string
    {
        return Cache::remember(self::cacheKey($key), now()->addHour(), function () use ($key): ?string {
            $row = static::query()->where('key', $key)->first();

            return $row?->value;
        });
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        Cache::forget(self::cacheKey($key));
    }

    /**
     * Admin override for public listen prefer-HLS policy.
     *
     * Returns true/false for explicit prefer, null for auto.
     * When no admin row exists, falls back to LISTEN_PREFER_HLS (.env / config).
     */
    public static function listenPreferHls(): ?bool
    {
        $stored = static::getValue(self::KEY_LISTEN_PREFER_HLS);

        if ($stored === null || $stored === '') {
            $env = config('streaming.listen.prefer_hls');

            return is_bool($env) ? $env : null;
        }

        return match ($stored) {
            'hls' => true,
            'whep' => false,
            'auto' => null,
            default => is_bool($env = config('streaming.listen.prefer_hls')) ? $env : null,
        };
    }

    /**
     * Stored admin choice, or null when unset (env fallback applies).
     */
    public static function listenPreferHlsChoice(): ?string
    {
        $stored = static::getValue(self::KEY_LISTEN_PREFER_HLS);

        if ($stored === null || $stored === '') {
            return null;
        }

        return in_array($stored, self::LISTEN_PREFER_HLS_OPTIONS, true) ? $stored : null;
    }

    /**
     * UI default when no admin override is stored yet (mirrors .env).
     */
    public static function listenPreferHlsFormDefault(): string
    {
        $choice = static::listenPreferHlsChoice();
        if ($choice !== null) {
            return $choice;
        }

        $env = config('streaming.listen.prefer_hls');
        if ($env === true) {
            return 'hls';
        }
        if ($env === false) {
            return 'whep';
        }

        return 'auto';
    }

    private static function cacheKey(string $key): string
    {
        return 'site_setting:'.$key;
    }
}
