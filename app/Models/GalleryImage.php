<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    protected $fillable = [
        'organization_id',
        'stream_id',
        'event_id',
        'uploaded_by',
        'path',
        'media_type',
        'duration_seconds',
        'poster_path',
        'caption',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isVideo(): bool
    {
        return ($this->media_type ?? 'image') === 'video';
    }

    public function isImage(): bool
    {
        return ! $this->isVideo();
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function posterUrl(): ?string
    {
        if (! is_string($this->poster_path) || $this->poster_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->poster_path);
    }

    /** @return array{id:int,url:string,caption:?string,type:string,duration_seconds:?int,poster_url:?string,created_at:?string} */
    public function toGalleryPayload(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'caption' => $this->caption,
            'type' => $this->isVideo() ? 'video' : 'image',
            'duration_seconds' => $this->duration_seconds,
            'poster_url' => $this->posterUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
