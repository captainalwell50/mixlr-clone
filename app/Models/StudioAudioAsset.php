<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class StudioAudioAsset extends Model
{
    public const PROVIDER_LOCAL = 'local';

    public const PROVIDER_PLATFORM = 'platform';

    public const PROVIDER_DRIVE = 'drive';

    protected $fillable = [
        'organization_id',
        'stream_id',
        'uploaded_by',
        'title',
        'original_filename',
        'path',
        'storage_provider',
        'external_id',
        'mime_type',
        'size_bytes',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isDrive(): bool
    {
        return $this->storage_provider === self::PROVIDER_DRIVE;
    }

    public function countsAgainstQuota(): bool
    {
        return in_array($this->storage_provider, [self::PROVIDER_LOCAL, self::PROVIDER_PLATFORM], true);
    }

    public function url(?Stream $stream = null): string
    {
        $stream ??= $this->stream;

        if ($this->isDrive()) {
            return URL::temporarySignedRoute(
                'studio.library.file',
                now()->addHours(12),
                ['stream' => $stream, 'asset' => $this],
            );
        }

        if ($this->storage_provider === self::PROVIDER_PLATFORM && config('object_storage.enabled')) {
            try {
                return Storage::disk((string) config('object_storage.disk', 's3'))
                    ->temporaryUrl($this->path, now()->addHours(6));
            } catch (\Throwable) {
                // fall through
            }
        }

        return Storage::disk('public')->url($this->path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toLibraryPayload(?Stream $stream = null): array
    {
        $stream ??= $this->stream;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_filename' => $this->original_filename,
            'url' => $this->url($stream),
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
            'storage_provider' => $this->storage_provider,
            'delete_url' => URL::temporarySignedRoute(
                'studio.library.destroy',
                now()->addHours(12),
                ['stream' => $stream, 'asset' => $this],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
