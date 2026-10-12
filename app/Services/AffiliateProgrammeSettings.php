<?php

namespace App\Services;

use App\Enums\AffiliateOutreachMethod;
use App\Enums\AffiliateStatus;
use App\Models\AffiliateAccount;
use App\Models\AffiliateSettingAudit;
use App\Models\SiteSetting;
use App\Models\User;

/**
 * Affiliate programme configuration stored in site settings.
 *
 * A stored JSON object overrides only the keys it contains. Commission,
 * penalty, and level values saved here are policy for future payments.
 * This class does not create commission rows, inspect Paystack charges,
 * or rewrite historical rates.
 */
final class AffiliateProgrammeSettings
{
    public const KEY = 'affiliate_programme';

    public const REGISTRATION_CLOSED = 'closed';

    public const REGISTRATION_FREE = 'free';

    public const REGISTRATION_PAID = 'paid';

    /** @var list<string> */
    public const REGISTRATION_MODES = [
        self::REGISTRATION_CLOSED,
        self::REGISTRATION_FREE,
        self::REGISTRATION_PAID,
    ];

    /** @var list<string> */
    private const BOOL_KEYS = [
        'enabled',
        'require_admin_approval',
        'outreach_visit_enabled',
        'outreach_direct_email_enabled',
        'outreach_phone_call_enabled',
        'recurring_commission_enabled',
        'retention_protection_enabled',
        'restore_on_new_paying_church',
        'restore_on_retention_recovery',
        'bank_details_required',
        'admin_can_release_prospects',
        'release_reason_required',
        'affiliate_notes_enabled',
        'show_renewal_approaching',
        'show_failed_renewal',
        'show_past_due',
        'show_disconnected_clients',
        'show_retention_percentage',
        'show_conversion_rate',
        'show_affiliate_level',
        'show_level_progress',
        'show_lifetime_earnings',
        'show_prospect_statistics',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const STRING_OPTIONS = [
        'registration_mode' => self::REGISTRATION_MODES,
        'attribution_model' => ['first_touch'],
        'commission_basis' => ['collected'],
        'payout_frequency' => ['monthly'],
        'payout_mode' => ['manual'],
    ];

    /**
     * @return array<string, bool|int|string>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'registration_mode' => self::REGISTRATION_FREE,
            'require_admin_approval' => true,
            'registration_fee_minor' => 0,
            'registration_currency' => 'NGN',
            'attribution_days' => 90,
            'attribution_model' => 'first_touch',
            'prospect_reservation_days' => 30,
            'prospect_active_limit' => 20,
            'outreach_visit_enabled' => true,
            'outreach_direct_email_enabled' => true,
            'outreach_phone_call_enabled' => true,
            'self_referral_protection' => true,
            'default_commission_bps' => 1000,
            'commission_hold_days' => 14,
            'payout_qualification_target' => 5,
            'recurring_commission_enabled' => true,
            'commission_basis' => 'collected',
            'inactivity_warning_months' => 1,
            'penalty_first_months' => 3,
            'penalty_first_bps' => 700,
            'penalty_second_months' => 6,
            'penalty_second_bps' => 500,
            'retention_protection_enabled' => true,
            'retention_waiver_bps' => 9000,
            'restore_on_new_paying_church' => true,
            'restore_on_retention_recovery' => true,
            'restored_commission_bps' => 1000,
            'demotion_grace_days' => 30,
            'payout_frequency' => 'monthly',
            'minimum_payout_minor' => 0,
            'payout_mode' => 'manual',
            'bank_details_required' => true,
            'admin_can_release_prospects' => true,
            'release_reason_required' => true,
            'affiliate_notes_enabled' => true,
            'prospect_note_max_length' => 2000,
            'reservation_renewal_enabled' => false,
            'max_approved_affiliates' => 0,
            'max_applications_per_month' => 0,
            'show_renewal_approaching' => false,
            'show_failed_renewal' => false,
            'show_past_due' => false,
            'show_disconnected_clients' => false,
            'show_retention_percentage' => false,
            'show_conversion_rate' => true,
            'show_affiliate_level' => false,
            'show_level_progress' => false,
            'show_lifetime_earnings' => false,
            'show_prospect_statistics' => true,
        ];
    }

    /**
     * @return list<array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool}>
     */
    public static function defaultLevels(): array
    {
        return [
            ['name' => 'Starter', 'min_active_paying_churches' => 0, 'display_order' => 1, 'enabled' => true],
            ['name' => 'Bronze', 'min_active_paying_churches' => 5, 'display_order' => 2, 'enabled' => true],
            ['name' => 'Professional', 'min_active_paying_churches' => 10, 'display_order' => 3, 'enabled' => true],
            ['name' => 'Gold', 'min_active_paying_churches' => 20, 'display_order' => 4, 'enabled' => true],
            ['name' => 'Embassy', 'min_active_paying_churches' => 40, 'display_order' => 5, 'enabled' => true],
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    public static function all(): array
    {
        $defaults = self::defaults();
        $merged = $defaults;

        foreach (self::stored() as $key => $value) {
            if (! array_key_exists($key, $defaults)) {
                continue;
            }

            $merged[$key] = self::cast($key, $value, $defaults[$key]);
        }

        return self::lockPolicy($merged);
    }

    /**
     * @return list<array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool}>
     */
    public static function levels(): array
    {
        $stored = self::stored()['levels'] ?? null;
        if (! is_array($stored)) {
            return self::defaultLevels();
        }

        $clean = [];
        foreach ($stored as $level) {
            if (! is_array($level)) {
                continue;
            }

            $name = trim((string) ($level['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $clean[] = [
                'name' => $name,
                'min_active_paying_churches' => max(0, (int) ($level['min_active_paying_churches'] ?? 0)),
                'display_order' => (int) ($level['display_order'] ?? 0),
                'enabled' => self::boolValue($level['enabled'] ?? true, true),
            ];
        }

        if ($clean === []) {
            return self::defaultLevels();
        }

        usort($clean, function (array $left, array $right): int {
            return [$left['display_order'], $left['min_active_paying_churches']]
                <=> [$right['display_order'], $right['min_active_paying_churches']];
        });

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values, User $actor): int
    {
        $current = self::all();
        $next = $current;

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, self::defaults())) {
                continue;
            }

            $next[$key] = self::cast($key, $value, self::defaults()[$key]);
        }

        $next = self::lockPolicy($next);

        return self::write($current, $next, self::levels(), self::levels(), $actor);
    }

    /**
     * @param  list<array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool}>  $levels
     */
    public static function saveLevels(array $levels, User $actor): int
    {
        $current = self::all();

        return self::write($current, $current, self::levels(), $levels, $actor);
    }

    public static function enabled(): bool
    {
        return (bool) self::all()['enabled'];
    }

    public static function registrationMode(): string
    {
        return (string) self::all()['registration_mode'];
    }

    public static function attributionDays(): int
    {
        return max(1, (int) self::all()['attribution_days']);
    }

    public static function prospectReservationDays(): int
    {
        return max(1, (int) self::all()['prospect_reservation_days']);
    }

    public static function prospectActiveLimit(): int
    {
        return max(0, (int) self::all()['prospect_active_limit']);
    }

    public static function noteMaxLength(): int
    {
        return max(1, (int) self::all()['prospect_note_max_length']);
    }

    public static function notesEnabled(): bool
    {
        return (bool) self::all()['affiliate_notes_enabled'];
    }

    public static function adminCanReleaseProspects(): bool
    {
        return (bool) self::all()['admin_can_release_prospects'];
    }

    public static function releaseReasonRequired(): bool
    {
        return (bool) self::all()['release_reason_required'];
    }

    public static function shows(string $key): bool
    {
        $settings = self::all();

        return (bool) ($settings[$key] ?? false);
    }

    public static function outreachEnabled(AffiliateOutreachMethod $method): bool
    {
        $key = match ($method) {
            AffiliateOutreachMethod::Visit => 'outreach_visit_enabled',
            AffiliateOutreachMethod::DirectEmail => 'outreach_direct_email_enabled',
            AffiliateOutreachMethod::PhoneCall => 'outreach_phone_call_enabled',
        };

        return (bool) self::all()[$key];
    }

    public static function registrationIsOpen(): bool
    {
        return self::registrationMode() !== self::REGISTRATION_CLOSED;
    }

    public static function canAcceptApplication(): bool
    {
        if (! self::registrationIsOpen()) {
            return false;
        }

        $max = (int) self::all()['max_applications_per_month'];
        if ($max <= 0) {
            return true;
        }

        $opened = AffiliateAccount::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return $opened < $max;
    }

    public static function canApproveAnother(): bool
    {
        $max = (int) self::all()['max_approved_affiliates'];
        if ($max <= 0) {
            return true;
        }

        $approved = AffiliateAccount::query()
            ->where('status', AffiliateStatus::Approved->value)
            ->count();

        return $approved < $max;
    }

    public static function displayValue(string $key, ?string $json): string
    {
        if ($json === null || $json === '') {
            return '—';
        }

        $value = json_decode($json, true);
        if (str_ends_with($key, '_bps') && is_numeric($value)) {
            return rtrim(rtrim(number_format(((float) $value) / 100, 2, '.', ''), '0'), '.').'%';
        }

        if (str_ends_with($key, '_minor') && is_numeric($value)) {
            return number_format(((int) $value) / 100, 2, '.', '');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
        }

        return (string) $value;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'enabled' => 'Programme enabled',
            'registration_mode' => 'Registration mode',
            'require_admin_approval' => 'Require admin approval',
            'registration_fee_minor' => 'Registration fee',
            'registration_currency' => 'Registration currency',
            'attribution_days' => 'Attribution window',
            'attribution_model' => 'Attribution model',
            'prospect_reservation_days' => 'Prospect reservation period',
            'prospect_active_limit' => 'Maximum active prospect reservations',
            'outreach_visit_enabled' => 'Plan Visit',
            'outreach_direct_email_enabled' => 'Plan Direct Email',
            'outreach_phone_call_enabled' => 'Plan Phone Call',
            'self_referral_protection' => 'Self-referral protection',
            'default_commission_bps' => 'Default commission rate',
            'commission_hold_days' => 'Commission hold period',
            'payout_qualification_target' => 'Paying churches required for payout',
            'recurring_commission_enabled' => 'Recurring commission',
            'commission_basis' => 'Commission basis',
            'inactivity_warning_months' => 'Inactivity warning',
            'penalty_first_months' => 'First penalty after',
            'penalty_first_bps' => 'First penalty rate',
            'penalty_second_months' => 'Second penalty after',
            'penalty_second_bps' => 'Second penalty rate',
            'retention_protection_enabled' => 'Retention protection',
            'retention_waiver_bps' => 'Minimum retention rate',
            'restore_on_new_paying_church' => 'Restore rate after a new paying church',
            'restore_on_retention_recovery' => 'Restore rate when retention recovers',
            'restored_commission_bps' => 'Restored commission rate',
            'demotion_grace_days' => 'Demotion grace period',
            'payout_frequency' => 'Payout frequency',
            'minimum_payout_minor' => 'Minimum payout amount',
            'payout_mode' => 'Payout mode',
            'bank_details_required' => 'Bank details required',
            'admin_can_release_prospects' => 'Admin can release a reservation',
            'release_reason_required' => 'Release reason required',
            'affiliate_notes_enabled' => 'Affiliate notes',
            'prospect_note_max_length' => 'Maximum note length',
            'reservation_renewal_enabled' => 'Self-service reservation renewal',
            'max_approved_affiliates' => 'Maximum approved affiliates',
            'max_applications_per_month' => 'Maximum applications per month',
            'show_renewal_approaching' => 'Affiliates see renewal approaching',
            'show_failed_renewal' => 'Affiliates see failed renewal',
            'show_past_due' => 'Affiliates see past due',
            'show_disconnected_clients' => 'Affiliates see disconnected clients',
            'show_retention_percentage' => 'Affiliates see retention',
            'show_conversion_rate' => 'Affiliates see conversion rate',
            'show_affiliate_level' => 'Affiliates see level',
            'show_level_progress' => 'Affiliates see progress to the next level',
            'show_lifetime_earnings' => 'Affiliates see lifetime earnings',
            'show_prospect_statistics' => 'Affiliates see prospect statistics',
            'levels' => 'Affiliate levels',
        ];
    }

    /**
     * @param  array<string, bool|int|string>  $oldScalars
     * @param  array<string, bool|int|string>  $newScalars
     * @param  list<array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool}>  $oldLevels
     * @param  list<array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool}>  $newLevels
     */
    private static function write(array $oldScalars, array $newScalars, array $oldLevels, array $newLevels, User $actor): int
    {
        $stored = self::stored();
        $changes = 0;

        foreach ($newScalars as $key => $value) {
            $stored[$key] = $value;
            if (! array_key_exists($key, $oldScalars) || $oldScalars[$key] !== $value) {
                self::audit($key, $oldScalars[$key] ?? null, $value, $actor);
                $changes++;
            }
        }

        if ($oldLevels !== $newLevels) {
            $stored['levels'] = $newLevels;
            self::audit('levels', $oldLevels, $newLevels, $actor);
            $changes++;
        }

        SiteSetting::putValue(self::KEY, json_encode($stored));

        return $changes;
    }

    private static function audit(string $key, mixed $old, mixed $new, User $actor): void
    {
        AffiliateSettingAudit::query()->create([
            'setting_key' => $key,
            'old_value' => json_encode($old),
            'new_value' => json_encode($new),
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, bool|int|string>  $settings
     * @return array<string, bool|int|string>
     */
    private static function lockPolicy(array $settings): array
    {
        $settings['self_referral_protection'] = true;
        $settings['reservation_renewal_enabled'] = false;
        $settings['attribution_model'] = 'first_touch';
        $settings['commission_basis'] = 'collected';
        $settings['payout_frequency'] = 'monthly';
        $settings['payout_mode'] = 'manual';

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored(): array
    {
        $raw = SiteSetting::getValue(self::KEY);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function cast(string $key, mixed $value, bool|int|string $default): bool|int|string
    {
        if (in_array($key, self::BOOL_KEYS, true)) {
            return self::boolValue($value, (bool) $default);
        }

        if (isset(self::STRING_OPTIONS[$key])) {
            $option = is_string($value) ? strtolower(trim($value)) : '';

            return in_array($option, self::STRING_OPTIONS[$key], true) ? $option : $default;
        }

        if ($key === 'registration_currency') {
            $letters = preg_replace('/[^A-Za-z]/', '', (string) $value) ?? '';
            $code = strtoupper(substr($letters, 0, 3));

            return strlen($code) === 3 ? $code : $default;
        }

        if (! is_numeric($value)) {
            return $default;
        }

        $number = (int) $value;

        return $number >= 0 ? $number : $default;
    }

    private static function boolValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }
}
