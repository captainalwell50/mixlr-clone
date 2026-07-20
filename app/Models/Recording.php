<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recording extends Model
{
    protected $fillable = [
        'stream_id',
        'relative_path',
        'duration_raw',
        'size_bytes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function durationLabel(): string
    {
        $raw = $this->duration_raw;
        if ($raw === null || $raw === '') {
            return '—';
        }

        $seconds = is_numeric($raw) ? (float) $raw : null;
        if ($seconds === null || $seconds < 0) {
            return (string) $raw;
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
