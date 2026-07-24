<?php

namespace App\Support;

class PlanFeatureCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function quotas(): array
    {
        return config('plan_features.quotas', []);
    }

    /** @return array<string, array<string, mixed>> */
    public static function features(): array
    {
        return config('plan_features.features', []);
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public static function featuresByGroup(): array
    {
        $grouped = [];
        foreach (self::features() as $key => $meta) {
            $group = (string) ($meta['group'] ?? 'General');
            $grouped[$group][$key] = $meta;
        }

        return $grouped;
    }

    /**
     * Defaults for a brand-new plan (all catalog keys).
     *
     * @return array<string, bool|int>
     */
    public static function defaultLimits(): array
    {
        $limits = [];
        foreach (self::quotas() as $key => $meta) {
            if (($meta['type'] ?? '') === 'storage_gb') {
                $limits['storage_bytes'] = ((int) ($meta['default'] ?? 2)) * 1024 * 1024 * 1024;
            } else {
                $limits[$key] = (int) ($meta['default'] ?? 0);
            }
        }
        foreach (self::features() as $key => $meta) {
            $limits[$key] = (bool) ($meta['default'] ?? false);
        }

        return $limits;
    }

    /**
     * Merge request input into a limits array.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, bool|int>
     */
    public static function limitsFromInput(array $input): array
    {
        $limits = self::defaultLimits();

        foreach (self::quotas() as $key => $meta) {
            if (($meta['type'] ?? '') === 'storage_gb') {
                $gb = (int) ($input['storage_gb'] ?? ($meta['default'] ?? 2));
                $gb = max((int) ($meta['min'] ?? 0), min((int) ($meta['max'] ?? 10240), $gb));
                $limits['storage_bytes'] = $gb * 1024 * 1024 * 1024;
                continue;
            }

            $value = (int) ($input[$key] ?? ($meta['default'] ?? 0));
            $value = max((int) ($meta['min'] ?? 0), min((int) ($meta['max'] ?? 100000), $value));
            $limits[$key] = $value;
        }

        foreach (array_keys(self::features()) as $key) {
            $limits[$key] = ! empty($input['features'][$key]);
        }

        return $limits;
    }

    /**
     * Human bullets for billing cards from a limits array.
     *
     * @param  array<string, mixed>|null  $limits
     * @return list<string>
     */
    public static function marketingBullets(?array $limits): array
    {
        $limits = $limits ?? [];
        $bullets = [];

        if (! empty($limits['live_streaming'])) {
            $bullets[] = 'Unlimited live streaming';
        }

        $maxRecordings = (int) ($limits['max_recordings'] ?? 0);
        if ($maxRecordings > 0) {
            $bullets[] = number_format($maxRecordings).' podcasts or uploads';
        }

        $streams = (int) ($limits['max_streams'] ?? 0);
        if ($streams > 0) {
            $bullets[] = $streams === 1
                ? '1 live stream'
                : $streams.' live streams';
        }

        $bytes = (int) ($limits['storage_bytes'] ?? 0);
        if ($bytes > 0) {
            $bullets[] = self::formatBytes($bytes).' platform storage';
        }

        foreach (self::features() as $key => $meta) {
            if (in_array($key, ['live_streaming'], true)) {
                continue;
            }
            if (! empty($limits[$key])) {
                $bullets[] = (string) $meta['label'];
            }
        }

        return $bullets;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            $gb = $bytes / (1024 ** 3);
            if ($gb >= 10) {
                return number_format($gb, 0).' GB';
            }

            $label = number_format($gb, 1, '.', '');
            $label = str_ends_with($label, '.0') ? substr($label, 0, -2) : $label;

            return $label.' GB';
        }

        return number_format(max(1, (int) round($bytes / (1024 ** 2)))).' MB';
    }

    public static function storageGbFromLimits(?array $limits): int
    {
        $bytes = (int) data_get($limits, 'storage_bytes', 2 * 1024 * 1024 * 1024);

        return max(0, (int) round($bytes / (1024 ** 3)));
    }
}
