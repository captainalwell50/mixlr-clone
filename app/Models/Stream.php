<?php

namespace App\Models;

use App\Enums\StreamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Stream extends Model
{
    protected $fillable = [
        'organization_id',
        'uuid',
        'stream_key',
        'title',
        'description',
        'is_public',
        'chat_enabled',
        'status',
        'started_at',
        'ended_at',
        'archive_path',
    ];

    protected $hidden = [
        'stream_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => StreamStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_public' => 'boolean',
            'chat_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Stream $stream): void {
            if (empty($stream->stream_key)) {
                $stream->stream_key = Str::random(40);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class)->latest('completed_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->latest('id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(StreamLike::class);
    }

    public function listenerSessions(): HasMany
    {
        return $this->hasMany(StreamListenerSession::class);
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(GalleryImage::class)->latest('id');
    }

    public function activeListenerCount(int $withinSeconds = 45): int
    {
        return $this->listenerSessions()
            ->where('last_seen_at', '>=', now()->subSeconds($withinSeconds))
            ->count();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function mediaPath(): string
    {
        return 'live/'.$this->uuid;
    }

    public function regenerateStreamKey(): void
    {
        $this->forceFill(['stream_key' => Str::random(40)])->save();
    }

    /**
     * Credential used for WHIP query / RTMP password (per-stream).
     */
    public function publishCredential(): string
    {
        return (string) $this->stream_key;
    }

    public function whipUrl(): string
    {
        $url = rtrim(config('streaming.mediamtx.webrtc_public_base'), '/').'/'.$this->mediaPath().'/whip';

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'pass' => $this->publishCredential(),
        ]);
    }

    /**
     * Browser listen path for Opus WHIP publishes (HLS/Opus is not playable in Chrome).
     */
    public function whepUrl(): string
    {
        return rtrim((string) config('streaming.mediamtx.webrtc_public_base'), '/').'/'.$this->mediaPath().'/whep';
    }

    public function hlsPlaylistUrl(): string
    {
        $cdn = config('streaming.mediamtx.hls_cdn_base');
        $base = (is_string($cdn) && $cdn !== '')
            ? $cdn
            : config('streaming.mediamtx.hls_public_base');

        return rtrim((string) $base, '/').'/'.$this->mediaPath().'/index.m3u8';
    }

    /**
     * OBS / ffmpeg: Server = rtmp base, Stream key = live/<uuid>?pass=<stream_key>
     * or password field = stream_key depending on client.
     */
    public function rtmpUrl(): string
    {
        $base = rtrim((string) config('streaming.mediamtx.rtmp_public_base'), '/');

        return $base.'/'.$this->mediaPath();
    }

    public function rtmpStreamKeyForObs(): string
    {
        return $this->mediaPath().'?pass='.$this->publishCredential();
    }
}
