<?php

namespace App\Models;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
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

    public function isLive(): bool
    {
        return $this->status === EventStatus::Live;
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

    public function activeListenerCount(int $withinSeconds = 45): int
    {
        return $this->listenerSessions()
            ->where('last_seen_at', '>=', now()->subSeconds($withinSeconds))
            ->count();
    }

    public function artworkUrl(): ?string
    {
        if (is_string($this->artwork_path) && $this->artwork_path !== '') {
            return $this->artwork_path;
        }

        return $this->organization?->artworkUrl();
    }
}
