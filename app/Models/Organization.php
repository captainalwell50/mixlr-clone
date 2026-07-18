<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'logo_path',
        'artwork_path',
        'theme_color',
        'support_url',
        'is_public',
        'branding_config',
    ];

    protected function casts(): array
    {
        return [
            'branding_config' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function follows(): HasMany
    {
        return $this->hasMany(ChannelFollow::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_follows')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function liveEvent(): ?Event
    {
        return $this->events()
            ->where('status', EventStatus::Live)
            ->latest('started_at')
            ->first();
    }

    public function themeColor(): string
    {
        return $this->theme_color
            ?: data_get($this->branding_config, 'accent', '#3d9b7a');
    }
}
