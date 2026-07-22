<?php

namespace App\Services;

use App\Models\Recording;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingStorageService
{
    public function objectStorageEnabled(): bool
    {
        if (! config('object_storage.enabled')) {
            return false;
        }

        $disk = (string) config('object_storage.disk', 's3');
        $bucket = config("filesystems.disks.{$disk}.bucket");

        return is_string($bucket) && $bucket !== '';
    }

    public function objectDisk(): Filesystem
    {
        return Storage::disk((string) config('object_storage.disk', 's3'));
    }

    public function localDisk(): Filesystem
    {
        return Storage::disk('mediamtx_recordings');
    }

    public function objectKeyFor(Recording $recording): string
    {
        if (filled($recording->object_key)) {
            return (string) $recording->object_key;
        }

        $prefix = (string) config('object_storage.prefix', 'live-mix');
        $streamUuid = $recording->stream?->uuid ?? 'unknown';
        $safe = ltrim(str_replace('\\', '/', $recording->relative_path), '/');

        return trim($prefix.'/recordings/'.$streamUuid.'/'.$safe, '/');
    }

    public function syncToObjectStorage(Recording $recording): bool
    {
        if (! $this->objectStorageEnabled()) {
            return false;
        }

        $local = $this->localDisk();
        if (! $local->exists($recording->relative_path)) {
            return false;
        }

        $key = $this->objectKeyFor($recording);
        $stream = $local->readStream($recording->relative_path);
        if ($stream === false) {
            return false;
        }

        try {
            $this->objectDisk()->put($key, $stream, [
                'visibility' => 'private',
                'ContentType' => $this->mimeForPath($recording->relative_path),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $recording->forceFill([
            'storage_disk' => (string) config('object_storage.disk', 's3'),
            'object_key' => $key,
            'synced_at' => now(),
            'size_bytes' => $recording->size_bytes ?: $local->size($recording->relative_path),
        ])->save();

        if (config('object_storage.delete_local_after_sync')) {
            $local->delete($recording->relative_path);
            $recording->forceFill(['local_deleted_at' => now()])->save();
        }

        return true;
    }

    public function exists(Recording $recording): bool
    {
        if (filled($recording->object_key) && $recording->synced_at) {
            try {
                if ($this->objectDisk()->exists($recording->object_key)) {
                    return true;
                }
            } catch (\Throwable) {
                // fall through to local
            }
        }

        if ($recording->local_deleted_at) {
            return false;
        }

        return $this->localDisk()->exists($recording->relative_path);
    }

    public function temporaryUrl(Recording $recording): ?string
    {
        if (! filled($recording->object_key) || ! $recording->synced_at) {
            return null;
        }

        $minutes = max(5, (int) config('object_storage.signed_url_minutes', 120));

        try {
            return $this->objectDisk()->temporaryUrl(
                $recording->object_key,
                now()->addMinutes($minutes),
                [
                    'ResponseContentType' => $this->mimeForPath($recording->relative_path),
                    'ResponseContentDisposition' => 'inline; filename="'.basename($recording->relative_path).'"',
                ]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function streamResponse(Recording $recording, bool $asDownload = false): StreamedResponse
    {
        if (filled($recording->object_key) && $recording->synced_at) {
            $disk = $this->objectDisk();
            $path = $recording->object_key;
            if ($disk->exists($path)) {
                return $asDownload
                    ? $disk->download($path, basename($recording->relative_path))
                    : $disk->response($path, basename($recording->relative_path), [
                        'Content-Type' => $this->mimeForPath($recording->relative_path),
                        'Accept-Ranges' => 'bytes',
                        'Cache-Control' => 'private, max-age=3600',
                    ]);
            }
        }

        $disk = $this->localDisk();
        if (! $disk->exists($recording->relative_path)) {
            abort(404);
        }

        return $asDownload
            ? $disk->download($recording->relative_path, basename($recording->relative_path))
            : $disk->response($recording->relative_path, basename($recording->relative_path), [
                'Content-Type' => $this->mimeForPath($recording->relative_path),
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=3600',
            ]);
    }

    public function deleteFiles(Recording $recording): void
    {
        if (filled($recording->object_key)) {
            try {
                $this->objectDisk()->delete($recording->object_key);
            } catch (\Throwable) {
                // ignore missing remote
            }
        }

        if (! $recording->local_deleted_at && filled($recording->relative_path)) {
            try {
                $this->localDisk()->delete($recording->relative_path);
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    public function mimeForPath(string $path): string
    {
        return match (Str::lower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4', 'm4a', 'm4v' => 'audio/mp4',
            'webm' => 'audio/webm',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }
}
