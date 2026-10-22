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

    public const KEY_WHATSAPP_SUPPORT_ENABLED = 'whatsapp_support_enabled';

    public const KEY_WHATSAPP_SUPPORT_NUMBER = 'whatsapp_support_number';

    public const KEY_WHATSAPP_DEFAULT_MESSAGE = 'whatsapp_default_message';

    public const KEY_MAIL_ENABLED = 'mail_enabled';

    public const KEY_MAIL_MAILER = 'mail_mailer';

    public const KEY_MAIL_HOST = 'mail_host';

    public const KEY_MAIL_PORT = 'mail_port';

    public const KEY_MAIL_USERNAME = 'mail_username';

    public const KEY_MAIL_PASSWORD = 'mail_password';

    public const KEY_MAIL_ENCRYPTION = 'mail_encryption';

    public const KEY_MAIL_FROM_ADDRESS = 'mail_from_address';

    public const KEY_MAIL_FROM_NAME = 'mail_from_name';

    public const KEY_MAIL_REPLY_TO = 'mail_reply_to';

    public const KEY_MAIL_LAST_TEST_AT = 'mail_last_test_at';

    public const KEY_MAIL_LAST_TEST_STATUS = 'mail_last_test_status';

    public const KEY_MAIL_LAST_TEST_ERROR = 'mail_last_test_error';

    public const KEY_MAIL_LAST_TEST_RECIPIENT = 'mail_last_test_recipient';

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

    protected static ?array $runtimeCache = null;

    protected static function booted(): void
    {
        static::saved(function () {
            static::clearRuntimeCache();
        });

        static::deleted(function () {
            static::clearRuntimeCache();
        });
    }

    public static function clearRuntimeCache(): void
    {
        static::$runtimeCache = null;
        Cache::forget('site_settings:all_v1');
    }

    public static function allValues(): array
    {
        if (static::$runtimeCache !== null) {
            return static::$runtimeCache;
        }

        try {
            static::$runtimeCache = Cache::remember('site_settings:all_v1', now()->addHour(), function (): array {
                return static::query()->pluck('value', 'key')->all();
            });
        } catch (\Throwable) {
            try {
                static::$runtimeCache = static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                static::$runtimeCache = [];
            }
        }

        return static::$runtimeCache ?? [];
    }

    public static function getValue(string $key): ?string
    {
        $all = static::allValues();

        return array_key_exists($key, $all) ? $all[$key] : null;
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        static::$runtimeCache = null;
        Cache::forget('site_settings:all_v1');
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

    public static function normalizeWhatsAppNumber(?string $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);

        // Reject if it contains letters or disallowed characters
        if (preg_match('/[a-zA-Z]/', $trimmed) || preg_match('/[^0-9+\s\-().]/', $trimmed)) {
            return null;
        }

        // Strip non-digit characters (+, spaces, hyphens, parentheses, etc.)
        $digits = preg_replace('/[^\d]/', '', $trimmed);

        // Strip leading 00 international dialing prefix (e.g. 00234... -> 234...)
        if (str_starts_with($digits, '00')) {
            $digits = preg_replace('/^00+/', '', $digits);
        }

        // Explicitly handle 11-digit Nigerian local format starting with 0 (e.g. 080XXXXXXXX -> 23480XXXXXXXX)
        // Never silently strip to 80XXXXXXXX.
        if (preg_match('/^0\d{10}$/', $digits)) {
            $digits = '234' . substr($digits, 1);
        }

        // E.164 international numbers never begin with 0. Reject malformed numbers starting with 0.
        if (str_starts_with($digits, '0')) {
            return null;
        }

        // Standard E.164 international numbers are typically 8 to 15 digits
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    public static function whatsappSupportEnabled(): bool
    {
        $stored = static::getValue(self::KEY_WHATSAPP_SUPPORT_ENABLED);
        if ($stored !== null && $stored !== '') {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }

        if (config()->has('services.whatsapp.enabled')) {
            $cfg = config('services.whatsapp.enabled');
            if ($cfg !== null && $cfg !== '') {
                return filter_var($cfg, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $env = env('WHATSAPP_SUPPORT_ENABLED');
        if ($env !== null && $env !== '') {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }

        return static::whatsappSupportNumber() !== null;
    }

    public static function whatsappSupportNumber(): ?string
    {
        $stored = static::getValue(self::KEY_WHATSAPP_SUPPORT_NUMBER);
        if (filled($stored)) {
            $normalized = static::normalizeWhatsAppNumber((string) $stored);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        // When admin has saved WhatsApp configuration, do not fall back to env number
        if (static::getValue(self::KEY_WHATSAPP_SUPPORT_ENABLED) !== null) {
            return null;
        }

        if (config()->has('services.whatsapp.number')) {
            $cfg = config('services.whatsapp.number');
            if ($cfg !== null && $cfg !== '') {
                return static::normalizeWhatsAppNumber((string) $cfg);
            }

            return null;
        }

        $env = env('WHATSAPP_SUPPORT_NUMBER');
        if (filled($env)) {
            return static::normalizeWhatsAppNumber((string) $env);
        }

        return null;
    }

    public static function whatsappSupportNumberRaw(): string
    {
        // Never expose environment values in UI input fields
        $stored = static::getValue(self::KEY_WHATSAPP_SUPPORT_NUMBER);

        return filled($stored) ? (string) $stored : '';
    }

    public static function whatsappDefaultMessageRaw(): string
    {
        // Never expose environment values in UI input fields
        $stored = static::getValue(self::KEY_WHATSAPP_DEFAULT_MESSAGE);

        return filled($stored) ? (string) $stored : '';
    }

    public static function whatsappSupportIsFromAdmin(): bool
    {
        return filled(static::getValue(self::KEY_WHATSAPP_SUPPORT_NUMBER));
    }

    public static function whatsappDefaultMessage(): string
    {
        $stored = static::getValue(self::KEY_WHATSAPP_DEFAULT_MESSAGE);
        if (filled($stored)) {
            return (string) $stored;
        }

        if (config()->has('services.whatsapp.default_message')) {
            $cfg = config('services.whatsapp.default_message');
            if (filled($cfg)) {
                return (string) $cfg;
            }
        }

        $env = env('WHATSAPP_DEFAULT_MESSAGE');
        if (filled($env)) {
            return (string) $env;
        }

        return "Hello SoundMix Support,\nI need help with SoundMix.";
    }

    public static function putWhatsAppSupport(?string $number, bool $enabled = true, ?string $message = null): void
    {
        static::putValue(self::KEY_WHATSAPP_SUPPORT_ENABLED, $enabled ? '1' : '0');

        $norm = static::normalizeWhatsAppNumber($number);
        if ($norm === null) {
            static::query()->where('key', self::KEY_WHATSAPP_SUPPORT_NUMBER)->delete();
            Cache::forget(self::cacheKey(self::KEY_WHATSAPP_SUPPORT_NUMBER));
        } else {
            static::putValue(self::KEY_WHATSAPP_SUPPORT_NUMBER, $norm);
        }

        $msg = is_string($message) ? trim($message) : '';
        if ($msg === '') {
            static::query()->where('key', self::KEY_WHATSAPP_DEFAULT_MESSAGE)->delete();
            Cache::forget(self::cacheKey(self::KEY_WHATSAPP_DEFAULT_MESSAGE));
        } else {
            static::putValue(self::KEY_WHATSAPP_DEFAULT_MESSAGE, $msg);
        }
    }

    public static function mailEnabled(): bool
    {
        $stored = static::getValue(self::KEY_MAIL_ENABLED);
        if ($stored !== null && $stored !== '') {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }

        // When not explicitly set, reflect stored mail_mailer setting
        $mailer = static::getValue(self::KEY_MAIL_MAILER);
        if ($mailer !== null && $mailer !== '') {
            return $mailer === 'smtp';
        }

        return false;
    }

    public static function putMailEnabled(bool $enabled): void
    {
        static::putValue(self::KEY_MAIL_ENABLED, $enabled ? '1' : '0');
    }

    public static function mailReplyTo(): string
    {
        $stored = static::getValue(self::KEY_MAIL_REPLY_TO);
        if (filled($stored) && filter_var($stored, FILTER_VALIDATE_EMAIL)) {
            return (string) $stored;
        }

        $env = config('mail.reply_to.address');
        if (filled($env) && filter_var($env, FILTER_VALIDATE_EMAIL)) {
            return (string) $env;
        }

        return static::supportEmail();
    }

    public static function putMailReplyTo(?string $replyTo): void
    {
        $replyTo = is_string($replyTo) ? trim($replyTo) : '';
        if ($replyTo === '') {
            static::query()->where('key', self::KEY_MAIL_REPLY_TO)->delete();
            Cache::forget(self::cacheKey(self::KEY_MAIL_REPLY_TO));

            return;
        }

        static::putValue(self::KEY_MAIL_REPLY_TO, $replyTo);
    }

    /**
     * @return array<string, mixed>
     */
    public static function mailSettings(): array
    {
        return app(\App\Services\MailConfigurationService::class)->getSettings();
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

        $env = env('MAIL_PASSWORD');

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
            static::clearRuntimeCache();
            Cache::forget(self::cacheKey(self::KEY_MAIL_PASSWORD));
            config(['mail.mailers.smtp.password' => env('MAIL_PASSWORD')]);

            return;
        }

        static::putValue(self::KEY_MAIL_PASSWORD, encrypt($plain));
    }

    public static function applyMailConfig(): void
    {
        app(\App\Services\MailConfigurationService::class)->apply();
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
