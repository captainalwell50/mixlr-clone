<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Recording;
use App\Models\StudioAudioAsset;
use Illuminate\Validation\ValidationException;

class StorageQuotaService
{
    /** Default when plan has no storage_bytes limit (bytes). */
    public const DEFAULT_BYTES = 2 * 1024 * 1024 * 1024; // 2 GiB

    public function limitBytes(Organization $organization): int
    {
        $organization->loadMissing('subscription.plan');
        $plan = $organization->subscription?->plan;

        if ($plan === null) {
            return (int) config('object_storage.default_org_quota_bytes', self::DEFAULT_BYTES);
        }

        return (int) data_get($plan->limits, 'storage_bytes', self::DEFAULT_BYTES);
    }

    public function usedBytes(Organization $organization): int
    {
        $studio = (int) StudioAudioAsset::query()
            ->where('organization_id', $organization->id)
            ->whereIn('storage_provider', ['local', 'platform'])
            ->sum('size_bytes');

        $streamIds = $organization->streams()->pluck('id');
        $recordings = (int) Recording::query()
            ->whereIn('stream_id', $streamIds)
            ->sum('size_bytes');

        return $studio + $recordings;
    }

    public function remainingBytes(Organization $organization): int
    {
        return max(0, $this->limitBytes($organization) - $this->usedBytes($organization));
    }

    /**
     * @return array{used_bytes:int, limit_bytes:int, remaining_bytes:int, used_label:string, limit_label:string}
     */
    public function summary(Organization $organization): array
    {
        $used = $this->usedBytes($organization);
        $limit = $this->limitBytes($organization);

        return [
            'used_bytes' => $used,
            'limit_bytes' => $limit,
            'remaining_bytes' => max(0, $limit - $used),
            'used_label' => $this->formatBytes($used),
            'limit_label' => $this->formatBytes($limit),
        ];
    }

    public function assertCanStore(Organization $organization, int $incomingBytes): void
    {
        if ($incomingBytes <= 0) {
            return;
        }

        $remaining = $this->remainingBytes($organization);
        if ($incomingBytes <= $remaining) {
            return;
        }

        $summary = $this->summary($organization);

        throw ValidationException::withMessages([
            'storage' => sprintf(
                'Platform storage full (%s of %s used). Free space, upgrade your plan, or save the file to Google Drive instead.',
                $summary['used_label'],
                $summary['limit_label'],
            ),
        ]);
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 ** 2) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 ** 3) {
            return number_format($bytes / (1024 ** 2), 1).' MB';
        }

        return number_format($bytes / (1024 ** 3), 2).' GB';
    }
}
