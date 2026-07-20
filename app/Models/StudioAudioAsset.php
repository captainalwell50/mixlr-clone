<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class StudioAudioAsset extends Model
{
    protected $fillable = [
        'organization_id',
        'stream_id',
        'uploaded_by',
        'title',
        'original_filename',
        'path',
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

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * @return array{
     *     id:int,
     *     title:string,
     *     original_filename:string,
     *     url:string,
     *     mime_type:?string,
     *     size_bytes:int,
     *     duration_seconds:?int,
     *     delete_url:string,
     *     created_at:?string
     * }
     */
    public function toLibraryPayload(?Stream $stream = null): array
    {
        $stream ??= $this->stream;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_filename' => $this->original_filename,
            'url' => $this->url(),
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
            'delete_url' => URL::temporarySignedRoute(
                'studio.library.destroy',
                now()->addHours(12),
                ['stream' => $stream, 'asset' => $this],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
