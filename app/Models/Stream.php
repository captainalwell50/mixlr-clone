<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use Illuminate\Database\Eloquent\Builder;
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
        'listen_background_path',
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

    /**
     * Open service event for Studio gallery (scheduled / live / paused).
     */
    public function openServiceEvent(): ?Event
    {
        return $this->events()
            ->whereIn('status', [EventStatus::Scheduled, EventStatus::Live, EventStatus::Paused])
            ->latest('id')
            ->first();
    }

    /**
     * Resolve which event's gallery to show.
     * Explicit event_id wins when it belongs to this stream; otherwise the open service event.
     */
    public function resolveGalleryEventId(?int $requestedEventId = null): ?int
    {
        if ($requestedEventId !== null) {
            $belongs = $this->events()->whereKey($requestedEventId)->exists();

            return $belongs ? $requestedEventId : null;
        }

        return $this->openServiceEvent()?->id;
    }

    /**
     * Gallery posts for a single service event. Empty when no event can be resolved.
     * Legacy rows with null event_id are excluded from "this service" views.
     *
     * @return Builder<GalleryImage>
     */
    public function serviceGalleryImages(?int $eventId = null): Builder
    {
        $resolvedId = $this->resolveGalleryEventId($eventId);

        if ($resolvedId === null) {
            return GalleryImage::query()->whereKey([]);
        }

        return GalleryImage::query()
            ->where('stream_id', $this->id)
            ->where('event_id', $resolvedId)
            ->latest('id');
    }

    public function studioAudioAssets(): HasMany
    {
        return $this->hasMany(StudioAudioAsset::class)->latest('id');
    }

    public function activeListenerCount(int $withinSeconds = 45): int
    {
        return $this->listenerSessions()
            ->where('last_seen_at', '>=', now()->subSeconds($withinSeconds))
            ->count();
    }

    public function listenBackgroundUrl(): ?string
    {
        $path = $this->listen_background_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
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
     * Low-latency listen / Studio self-monitor (WHEP). Always available as fallback.
     */
    public function whepUrl(): string
    {
        return rtrim((string) config('streaming.mediamtx.webrtc_public_base'), '/').'/'.$this->mediaPath().'/whep';
    }

    /**
     * MediaMTX path segment for public HLS (optional /aac sidecar for browser-safe audio).
     */
    public function hlsMediaPath(): string
    {
        $path = $this->mediaPath();

        if (config('streaming.listen.hls_aac_sidecar')) {
            return $path.'/aac';
        }

        return $path;
    }

    public function hlsPlaylistUrl(): string
    {
        $cdn = config('streaming.mediamtx.hls_cdn_base');
        $base = (is_string($cdn) && $cdn !== '')
            ? $cdn
            : config('streaming.mediamtx.hls_public_base');

        return rtrim((string) $base, '/').'/'.$this->hlsMediaPath().'/index.m3u8';
    }

    /**
     * Prefer CDN/HLS for mass public listen when configured; Studio stays on WHEP.
     *
     * Admin site setting overrides LISTEN_PREFER_HLS when set; otherwise .env/config.
     */
    public function preferHlsListen(): bool
    {
        return static::resolvePreferHlsListen();
    }

    /**
     * Resolve public listen prefer-HLS from an explicit true/false/null (auto) policy.
     * Shared by Stream::preferHlsListen() and the admin settings status panel.
     */
    public static function resolvePreferHlsListen(?bool $explicit = null): bool
    {
        $explicit ??= SiteSetting::listenPreferHls();

        if ($explicit === true) {
            return true;
        }
        if ($explicit === false) {
            return false;
        }

        $cdn = config('streaming.mediamtx.hls_cdn_base');
        if (is_string($cdn) && $cdn !== '') {
            return true;
        }

        return (bool) config('streaming.listen.hls_aac_sidecar');
    }

    /**
     * Admin-facing snapshot of which listen mode is effective and why.
     *
     * @return array{
     *     prefers_hls: bool,
     *     effective_mode: string,
     *     effective_label: string,
     *     configured_choice: ?string,
     *     configured_label: string,
     *     form_value: string,
     *     is_auto: bool,
     *     auto_reason: ?string,
     *     policy_source: string,
     *     policy_source_label: string,
     *     cdn_configured: bool,
     *     cdn_base: ?string,
     *     cdn_host: ?string,
     *     aac_sidecar: bool,
     *     env_default: mixed,
     *     env_label: string
     * }
     */
    public static function listenPlaybackStatus(): array
    {
        $explicit = SiteSetting::listenPreferHls();
        $choice = SiteSetting::listenPreferHlsChoice();
        $formValue = SiteSetting::listenPreferHlsFormDefault();
        $prefersHls = static::resolvePreferHlsListen($explicit);
        $cdnBase = config('streaming.mediamtx.hls_cdn_base');
        $cdnConfigured = is_string($cdnBase) && $cdnBase !== '';
        $cdnBase = $cdnConfigured ? $cdnBase : null;
        $cdnHost = null;
        if ($cdnBase !== null) {
            $host = parse_url($cdnBase, PHP_URL_HOST);
            $cdnHost = is_string($host) && $host !== '' ? $host : $cdnBase;
        }
        $aacSidecar = (bool) config('streaming.listen.hls_aac_sidecar');
        $envDefault = config('streaming.listen.prefer_hls');
        $isAuto = $explicit === null;

        $autoReason = null;
        if ($isAuto) {
            if ($cdnConfigured && $aacSidecar) {
                $autoReason = 'Auto → HLS because CDN base + AAC sidecar';
            } elseif ($cdnConfigured) {
                $autoReason = 'Auto → HLS because CDN base is set';
            } elseif ($aacSidecar) {
                $autoReason = 'Auto → HLS because AAC sidecar is on';
            } else {
                $autoReason = 'Auto → WHEP because CDN base is not set and AAC sidecar is off';
            }
        }

        $configuredLabel = match ($formValue) {
            'hls' => 'CDN HLS (forced)',
            'whep' => 'WHEP (forced)',
            default => 'Auto',
        };
        if ($choice === null) {
            $configuredLabel .= ' · from .env';
        } else {
            $configuredLabel .= ' · admin saved';
        }

        $envLabel = match (true) {
            $envDefault === true => 'LISTEN_PREFER_HLS=true',
            $envDefault === false => 'LISTEN_PREFER_HLS=false',
            default => 'LISTEN_PREFER_HLS unset (auto)',
        };

        $policySource = $choice !== null ? 'admin' : 'env';
        $policySourceLabel = $choice !== null
            ? 'Admin override ('.$choice.')'
            : '.env fallback ('.$envLabel.')';

        return [
            'prefers_hls' => $prefersHls,
            'effective_mode' => $prefersHls ? 'hls' : 'whep',
            'effective_label' => $prefersHls ? 'CDN HLS' : 'WHEP (Low latency)',
            'configured_choice' => $choice,
            'configured_label' => $configuredLabel,
            'form_value' => $formValue,
            'is_auto' => $isAuto,
            'auto_reason' => $autoReason,
            'policy_source' => $policySource,
            'policy_source_label' => $policySourceLabel,
            'cdn_configured' => $cdnConfigured,
            'cdn_base' => $cdnBase,
            'cdn_host' => $cdnHost,
            'aac_sidecar' => $aacSidecar,
            'env_default' => $envDefault,
            'env_label' => $envLabel,
        ];
    }

    /**
     * Primary playback mode for public listen clients (`hls` or `whep`).
     */
    public function playbackMode(): string
    {
        return $this->preferHlsListen() ? 'hls' : 'whep';
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
