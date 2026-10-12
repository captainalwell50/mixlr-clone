<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    public const KEY_LISTEN_PREFER_HLS = 'listen_prefer_hls';

    /** Unix timestamp: web Studio tabs should reload when this is newer than page load. */
    public const KEY_STUDIO_RELOAD_AT = 'studio_reload_at';

    /** JSON map of stream UUID => unix timestamp for a targeted Studio reload. */
    public const KEY_STUDIO_RELOAD_STREAMS = 'studio_reload_streams';

    public const KEY_VM_HOLD_PEAK_UNTIL = 'vm_hold_peak_until';

    public const KEY_PAYSTACK_PUBLIC = 'paystack_public_key';

    public const KEY_PAYSTACK_SECRET = 'paystack_secret_key';

    public const KEY_PAYSTACK_CURRENCY = 'paystack_currency';

    public const KEY_SUPPORT_EMAIL = 'support_email';

    public const KEY_MAIL_MAILER = 'mail_mailer';

    public const KEY_MAIL_HOST = 'mail_host';

    public const KEY_MAIL_PORT = 'mail_port';

    public const KEY_MAIL_USERNAME = 'mail_username';

    public const KEY_MAIL_PASSWORD = 'mail_password';

    public const KEY_MAIL_ENCRYPTION = 'mail_encryption';

    public const KEY_MAIL_FROM_ADDRESS = 'mail_from_address';

    public const KEY_MAIL_FROM_NAME = 'mail_from_name';

    public const KEY_EMAIL_ROUTING_ACTIVE_DESTINATION = 'email_routing_active_destination';

    public const KEY_EMAIL_ROUTING_PENDING_DESTINATION = 'email_routing_pending_destination';

    public const KEY_EMAIL_ROUTING_DESTINATION_STATUS = 'email_routing_destination_status';

    public const KEY_EMAIL_ROUTING_LAST_SYNCED_AT = 'email_routing_last_synced_at';

    public const KEY_OPUS_SEND_KBPS = 'opus_send_kbps';

    /** @var list<string> */
    public const LISTEN_PREFER_HLS_OPTIONS = ['hls', 'whep', 'auto'];

    /** Stereo Opus send rates, in kbps. 510 is the codec maximum. */
    public const OPUS_SEND_KBPS_OPTIONS = [128, 160, 192, 256, 320, 510];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key): ?string
    {
        try {
            return Cache::remember(self::cacheKey($key), now()->addHour(), function () use ($key): ?string {
                $row = static::query()->where('key', $key)->first();

                return $row?->value;
            });
        } catch (\Throwable) {
            return static::query()->where('key', $key)->first()?->value;
        }
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
     * Unix time web Studio last asked tabs to reload. 0 = never.
     */
    public static function studioReloadAt(?string $streamUuid = null): int
    {
        $global = (int) (static::getValue(self::KEY_STUDIO_RELOAD_AT) ?: 0);
        if ($streamUuid === null || $streamUuid === '') {
            return $global;
        }

        $map = json_decode((string) (static::getValue(self::KEY_STUDIO_RELOAD_STREAMS) ?: '{}'), true);
        if (! is_array($map)) {
            return $global;
        }

        return max($global, (int) ($map[$streamUuid] ?? 0));
    }

    public static function bumpStudioReload(?string $streamUuid = null): int
    {
        $at = time();
        if ($streamUuid) {
            $map = json_decode((string) (static::getValue(self::KEY_STUDIO_RELOAD_STREAMS) ?: '{}'), true);
            if (! is_array($map)) {
                $map = [];
            }
            $cutoff = $at - 86400;
            $map = array_filter(
                $map,
                fn ($value): bool => (int) $value >= $cutoff,
            );
            $map[$streamUuid] = $at;
            static::putValue(self::KEY_STUDIO_RELOAD_STREAMS, json_encode($map));
        } else {
            static::putValue(self::KEY_STUDIO_RELOAD_AT, (string) $at);
        }

        return $at;
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
     * Stereo Opus send rate chosen in platform settings, in kbps.
     * Unset or unknown values stay at 320.
     */
    public static function opusSendKbps(): int
    {
        $stored = (int) static::getValue(self::KEY_OPUS_SEND_KBPS);

        return in_array($stored, self::OPUS_SEND_KBPS_OPTIONS, true) ? $stored : 320;
    }

    public static function opusSendBitrate(): int
    {
        return static::opusSendKbps() * 1000;
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

    public static function paystackPublicKey(): ?string
    {
        $stored = static::getValue(self::KEY_PAYSTACK_PUBLIC);

        return filled($stored) ? $stored : (filled($env = config('services.paystack.public_key')) ? (string) $env : null);
    }

    public static function paystackSecretKey(): ?string
    {
        $stored = static::getValue(self::KEY_PAYSTACK_SECRET);
        if (filled($stored)) {
            try {
                $plain = decrypt($stored);

                return is_string($plain) && $plain !== '' ? $plain : null;
            } catch (\Throwable) {
                // Fall through to .env if the stored blob cannot be decrypted.
            }
        }

        $env = config('services.paystack.secret_key');

        return filled($env) ? (string) $env : null;
    }

    public static function paystackCurrency(): string
    {
        $stored = static::getValue(self::KEY_PAYSTACK_CURRENCY);
        if (is_string($stored) && preg_match('/^[A-Z]{3}$/', strtoupper($stored))) {
            return strtoupper($stored);
        }

        $env = config('services.paystack.currency');

        return is_string($env) && $env !== '' ? strtoupper($env) : 'NGN';
    }

    public static function paystackSecretIsFromAdmin(): bool
    {
        return filled(static::getValue(self::KEY_PAYSTACK_SECRET));
    }

    public static function maskKey(?string $plain): ?string
    {
        if (! is_string($plain) || $plain === '') {
            return null;
        }

        $len = strlen($plain);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($plain, 0, 4).str_repeat('•', min(18, $len - 8)).substr($plain, -4);
    }

    public static function putPaystackSecret(?string $plain): void
    {
        if ($plain === null || $plain === '') {
            static::query()->where('key', self::KEY_PAYSTACK_SECRET)->delete();
            Cache::forget(self::cacheKey(self::KEY_PAYSTACK_SECRET));

            return;
        }

        static::putValue(self::KEY_PAYSTACK_SECRET, encrypt($plain));
    }

    public static function supportEmail(): string
    {
        $stored = static::getValue(self::KEY_SUPPORT_EMAIL);
        if (is_string($stored) && filter_var($stored, FILTER_VALIDATE_EMAIL)) {
            return $stored;
        }

        $fallback = config('app.support_email_fallback') ?: config('app.support_email');

        return is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL)
            ? $fallback
            : 'support@soundmix.live';
    }

    public static function supportEmailIsFromAdmin(): bool
    {
        $stored = static::getValue(self::KEY_SUPPORT_EMAIL);

        return is_string($stored) && filter_var($stored, FILTER_VALIDATE_EMAIL);
    }

    public static function putSupportEmail(?string $email): void
    {
        $email = is_string($email) ? trim($email) : '';
        if ($email === '') {
            static::query()->where('key', self::KEY_SUPPORT_EMAIL)->delete();
            Cache::forget(self::cacheKey(self::KEY_SUPPORT_EMAIL));

            return;
        }

        static::putValue(self::KEY_SUPPORT_EMAIL, $email);
    }

    /**
     * @return array{
     *     mailer: string,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: ?string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     from_admin: bool,
     *     ready: bool
     * }
     */
    public static function mailSettings(): array
    {
        $mailer = static::mailStored(self::KEY_MAIL_MAILER) ?? (string) config('mail.default', 'log');
        $host = static::mailStored(self::KEY_MAIL_HOST) ?? (string) config('mail.mailers.smtp.host', 'smtp.gmail.com');
        $port = (int) (static::mailStored(self::KEY_MAIL_PORT) ?? config('mail.mailers.smtp.port', 587));
        $username = static::mailStored(self::KEY_MAIL_USERNAME) ?? (string) config('mail.mailers.smtp.username', '');
        $encryption = strtolower((string) (static::mailStored(self::KEY_MAIL_ENCRYPTION) ?? config('mail.mailers.smtp.encryption') ?? 'tls'));
        if (! in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        $fromAddress = static::mailStored(self::KEY_MAIL_FROM_ADDRESS)
            ?? (string) config('mail.from.address', '');
        $fromName = static::mailStored(self::KEY_MAIL_FROM_NAME)
            ?? (string) config('mail.from.name', config('app.name', 'Sound Mix Live'));

        $password = static::mailPassword();

        return [
            'mailer' => in_array($mailer, ['smtp', 'log'], true) ? $mailer : 'log',
            'host' => $host !== '' ? $host : 'smtp.gmail.com',
            'port' => $port > 0 ? $port : 587,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'from_admin' => filled(static::getValue(self::KEY_MAIL_MAILER)),
            'ready' => $mailer === 'smtp' && filled($username) && filled($password),
        ];
    }

    public static function mailPassword(): ?string
    {
        $stored = static::getValue(self::KEY_MAIL_PASSWORD);
        if (filled($stored)) {
            try {
                $plain = decrypt($stored);

                return is_string($plain) && $plain !== '' ? $plain : null;
            } catch (\Throwable) {
            }
        }

        $env = config('mail.mailers.smtp.password');

        return filled($env) ? (string) $env : null;
    }

    public static function mailPasswordIsFromAdmin(): bool
    {
        return filled(static::getValue(self::KEY_MAIL_PASSWORD));
    }

    public static function putMailPassword(?string $plain): void
    {
        if ($plain === null || $plain === '') {
            static::query()->where('key', self::KEY_MAIL_PASSWORD)->delete();
            Cache::forget(self::cacheKey(self::KEY_MAIL_PASSWORD));

            return;
        }

        static::putValue(self::KEY_MAIL_PASSWORD, encrypt($plain));
    }

    public static function applyMailConfig(): void
    {
        if (! filled(static::getValue(self::KEY_MAIL_MAILER))) {
            return;
        }

        $mail = static::mailSettings();
        $scheme = $mail['encryption'] === 'ssl' || $mail['port'] === 465 ? 'smtps' : 'smtp';
        $encryption = $mail['encryption'] === 'none' ? null : $mail['encryption'];

        config([
            'mail.default' => $mail['mailer'],
            'mail.mailers.smtp.host' => $mail['host'],
            'mail.mailers.smtp.port' => $mail['port'],
            'mail.mailers.smtp.username' => $mail['username'] !== '' ? $mail['username'] : null,
            'mail.mailers.smtp.password' => $mail['password'],
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.from.address' => $mail['from_address'] !== '' ? $mail['from_address'] : config('mail.from.address'),
            'mail.from.name' => $mail['from_name'] !== '' ? $mail['from_name'] : config('app.name'),
        ]);

        try {
            app('mail.manager')->purge('smtp');
        } catch (\Throwable) {
        }
    }

    public static function emailRoutingActiveDestination(): ?string
    {
        $stored = static::getValue(self::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION);
        if (filled($stored)) {
            return (string) $stored;
        }

        $env = config('services.cloudflare.default_destination');
        if (filled($env)) {
            return (string) $env;
        }

        return null;
    }

    public static function emailRoutingPendingDestination(): ?string
    {
        $stored = static::getValue(self::KEY_EMAIL_ROUTING_PENDING_DESTINATION);

        return filled($stored) ? (string) $stored : null;
    }

    public static function emailRoutingDestinationStatus(): string
    {
        $stored = static::getValue(self::KEY_EMAIL_ROUTING_DESTINATION_STATUS);

        return filled($stored) ? (string) $stored : 'verified';
    }

    public static function emailRoutingLastSyncedAt(): ?string
    {
        $stored = static::getValue(self::KEY_EMAIL_ROUTING_LAST_SYNCED_AT);

        return filled($stored) ? (string) $stored : null;
    }

    private static function mailStored(string $key): ?string
    {
        $stored = static::getValue($key);

        return filled($stored) ? (string) $stored : null;
    }

    private static function cacheKey(string $key): string
    {
        return 'site_setting:'.$key;
    }
}
