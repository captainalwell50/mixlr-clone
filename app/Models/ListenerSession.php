<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListenerSession extends Model
{
    protected $fillable = [
        'event_id',
        'session_key',
        'user_id',
        'started_at',
        'last_seen_at',
        'country',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
