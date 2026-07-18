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
}
