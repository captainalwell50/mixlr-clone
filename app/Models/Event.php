<?php

namespace App\Models;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Services\ListenerPresenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'organization_id',
        'stream_id',
        'uuid',
        'title',
        'description',
        'artwork_path',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status',
        'access',
        'access_password',
        'chat_enabled',
        'show_listener_count',
        'scripture_ref',
        'scripture_text',
        'scripture_updated_at',
        'song_id',
        'song_title',
        'song_text',
        'song_slide_index',
        'song_slide_count',
        'song_updated_at',
    ];

    protected $hidden = [
        'access_password',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'access' => EventAccess::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'chat_enabled' => 'boolean',
            'show_listener_count' => 'boolean',
            'scripture_updated_at' => 'datetime',
            'song_updated_at' => 'datetime',
            'song_slide_index' => 'integer',
            'song_slide_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function hearts(): HasMany
    {
        return $this->hasMany(EventHeart::class);
    }

    public function listenerSessions(): HasMany
    {
        return $this->hasMany(ListenerSession::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class)->latest('completed_at');
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(GalleryImage::class)->latest('id');
    }

    public function displaySong(): BelongsTo
    {
        return $this->belongsTo(DisplaySong::class, 'song_id');
    }

    public function isLive(): bool
    {
        return $this->status === EventStatus::Live;
    }

    public function isPaused(): bool
    {
        return $this->status === EventStatus::Paused;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [EventStatus::Scheduled, EventStatus::Live, EventStatus::Paused], true);
    }

    public function isDiscoverable(): bool
    {
        return $this->access === EventAccess::Public;
    }

    public function setAccessPasswordAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['access_password'] = null;

            return;
        }

        // Already hashed
        if (str_starts_with($value, '$2y$') || str_starts_with($value, '$2a$')) {
            $this->attributes['access_password'] = $value;

            return;
        }

        $this->attributes['access_password'] = Hash::make($value);
    }

    public function checkAccessPassword(?string $plain): bool
    {
        if ($this->access !== EventAccess::Private) {
            return true;
        }

        if ($this->access_password === null || $plain === null) {
            return false;
        }

        return Hash::check($plain, $this->access_password);
    }

    public function activeListenerCount(int $withinSeconds = ListenerPresenceService::HEARTBEAT_SECONDS): int
    {
        return app(ListenerPresenceService::class)->countForEvent($this, $withinSeconds);
    }

    public function artworkUrl(): ?string
    {
        if (is_string($this->artwork_path) && $this->artwork_path !== '') {
            return $this->artwork_path;
        }

        return $this->organization?->artworkUrl();
    }

    public function hasLiveScripture(): bool
    {
        return filled($this->scripture_ref) && filled($this->scripture_text);
    }

    public function hasLiveSong(): bool
    {
        return filled($this->song_title) && filled($this->song_text);
    }

    /**
     * Last successful listen-board cue wins (scripture vs song).
     *
     * @return 'scripture'|'song'|null
     */
    public function liveBoardMode(): ?string
    {
        $scripture = $this->hasLiveScripture();
        $song = $this->hasLiveSong();

        if ($scripture && $song) {
            $scriptureAt = $this->scripture_updated_at;
            $songAt = $this->song_updated_at;
            if ($scriptureAt && $songAt) {
                return $scriptureAt->greaterThanOrEqualTo($songAt) ? 'scripture' : 'song';
            }
            if ($scriptureAt) {
                return 'scripture';
            }
            if ($songAt) {
                return 'song';
            }

            return 'scripture';
        }

        if ($song) {
            return 'song';
        }

        if ($scripture) {
            return 'scripture';
        }

        return null;
    }

    /** @return array{ref: string, text: string, version: string, updated_at: string|null}|null */
    public function liveScripturePayload(): ?array
    {
        if ($this->liveBoardMode() !== 'scripture') {
            return null;
        }

        return [
            'ref' => (string) $this->scripture_ref,
            'text' => (string) $this->scripture_text,
            'version' => 'KJV',
            'updated_at' => $this->scripture_updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int|null, title: string, text: string, slide_index: int, slide_count: int, updated_at: string|null}|null
     */
    public function liveSongPayload(): ?array
    {
        if ($this->liveBoardMode() !== 'song') {
            return null;
        }

        return [
            'id' => $this->song_id ? (int) $this->song_id : null,
            'title' => (string) $this->song_title,
            'text' => (string) $this->song_text,
            'slide_index' => (int) ($this->song_slide_index ?? 0),
            'slide_count' => (int) ($this->song_slide_count ?? 1),
            'updated_at' => $this->song_updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{live_board: 'scripture'|'song'|null, scripture: array<string, mixed>|null, song: array<string, mixed>|null}
     */
    public function liveBoardPayload(): array
    {
        $mode = $this->liveBoardMode();

        return [
            'live_board' => $mode,
            'scripture' => $this->liveScripturePayload(),
            'song' => $this->liveSongPayload(),
        ];
    }

    public function cueLiveScripture(string $ref, string $text): void
    {
        $this->forceFill([
            'scripture_ref' => $ref,
            'scripture_text' => $text,
            'scripture_updated_at' => now(),
            'song_id' => null,
            'song_title' => null,
            'song_text' => null,
            'song_slide_index' => null,
            'song_slide_count' => null,
            'song_updated_at' => null,
        ])->save();
    }

    public function cueLiveSong(?int $songId, string $title, string $text, int $slideIndex, int $slideCount): void
    {
        $this->forceFill([
            'song_id' => $songId,
            'song_title' => $title,
            'song_text' => $text,
            'song_slide_index' => $slideIndex,
            'song_slide_count' => $slideCount,
            'song_updated_at' => now(),
            'scripture_ref' => null,
            'scripture_text' => null,
            'scripture_updated_at' => null,
        ])->save();
    }

    public function clearLiveScripture(): void
    {
        $clearStaleSong = $this->liveBoardMode() === 'scripture';
        $this->forceFill([
            'scripture_ref' => null,
            'scripture_text' => null,
            'scripture_updated_at' => now(),
            ...($clearStaleSong ? [
                'song_id' => null,
                'song_title' => null,
                'song_text' => null,
                'song_slide_index' => null,
                'song_slide_count' => null,
                'song_updated_at' => null,
            ] : []),
        ])->save();
    }

    public function clearLiveSong(): void
    {
        $clearStaleScripture = $this->liveBoardMode() === 'song';
        $this->forceFill([
            'song_id' => null,
            'song_title' => null,
            'song_text' => null,
            'song_slide_index' => null,
            'song_slide_count' => null,
            'song_updated_at' => now(),
            ...($clearStaleScripture ? [
                'scripture_ref' => null,
                'scripture_text' => null,
                'scripture_updated_at' => null,
            ] : []),
        ])->save();
    }
}
