<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recording extends Model
{
    public const SOURCE_MEDIAMTX = 'mediamtx';

    public const SOURCE_STUDIO = 'studio';

    protected $fillable = [
        'stream_id',
        'event_id',
        'title',
        'source',
        'is_public',
        'relative_path',
        'storage_disk',
        'object_key',
        'synced_at',
        'local_deleted_at',
        'duration_raw',
        'size_bytes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
            'local_deleted_at' => 'datetime',
        ];
    }

    public function scopePublicArchive($query)
    {
        return $query->where('is_public', true);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function displayTitle(): string
    {
        $this->loadMissing('event', 'stream.organization');

        $eventTitle = filled($this->event?->title) ? trim((string) $this->event->title) : null;
        $customTitle = filled($this->title) ? trim((string) $this->title) : null;
        $streamTitle = filled($this->stream?->title) ? trim((string) $this->stream->title) : null;
        $channelName = filled($this->stream?->organization?->name)
            ? trim((string) $this->stream->organization->name)
            : null;

        // Prefer an explicit podcast title, unless it was just a copy of the channel/stream name.
        if ($customTitle !== null
            && ! $this->isGenericChannelTitle($customTitle, $streamTitle, $channelName)) {
            return $customTitle;
        }

        // Event name is the public label for a recorded live.
        if ($eventTitle !== null) {
            return $eventTitle;
        }

        if ($customTitle !== null) {
            return $customTitle;
        }

        // Legacy MediaMTX chunks with no event — date stamp, not channel name.
        if ($this->completed_at) {
            return 'Live · '.$this->completed_at
                ->timezone(config('app.timezone'))
                ->format('M j, g:i A');
        }

        return 'Podcast';
    }

    private function isGenericChannelTitle(string $title, ?string $streamTitle, ?string $channelName): bool
    {
        $normalized = mb_strtolower(trim($title));

        foreach ([$streamTitle, $channelName] as $candidate) {
            if ($candidate !== null && mb_strtolower(trim($candidate)) === $normalized) {
                return true;
            }
        }

        return false;
    }

    public function isCloudSynced(): bool
    {
        return $this->synced_at !== null && filled($this->object_key);
    }

    /** Duration in seconds when known (Studio uploads store numeric seconds in duration_raw). */
    public function durationSeconds(): ?float
    {
        $raw = $this->duration_raw;
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $seconds = (float) $raw;

        return $seconds >= 0 ? $seconds : null;
    }

    public function durationLabel(): string
    {
        $seconds = $this->durationSeconds();
        if ($seconds === null) {
            $raw = $this->duration_raw;

            return ($raw === null || $raw === '') ? '—' : (string) $raw;
        }

        $total = (int) round($seconds);
        $h = intdiv($total, 3600);
        $m = intdiv($total % 3600, 60);
        $s = $total % 60;

        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }

        return sprintf('%d:%02d', $m, $s);
    }

    public function sizeLabel(): string
    {
        if (! $this->size_bytes) {
            return '—';
        }

        return number_format($this->size_bytes / 1024 / 1024, 1).' MB';
    }
}
