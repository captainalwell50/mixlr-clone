<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisplaySong extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'slides',
    ];

    protected function casts(): array
    {
        return [
            'slides' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Normalize slides from an array or blank-line-separated body.
     *
     * @param  list<string>|string|null  $slides
     * @return list<string>
     */
    public static function normalizeSlides(array|string|null $slides): array
    {
        if (is_string($slides)) {
            $parts = preg_split("/\n\s*\n/", $slides) ?: [];
            $slides = $parts;
        }

        if (! is_array($slides)) {
            return [];
        }

        $out = [];
        foreach ($slides as $slide) {
            $text = trim((string) $slide);
            if ($text === '') {
                continue;
            }
            $out[] = $text;
        }

        return array_values($out);
    }

    public function slideAt(int $index): ?string
    {
        $slides = $this->slides;
        if (! is_array($slides) || $slides === []) {
            return null;
        }

        if ($index < 0 || $index >= count($slides)) {
            return null;
        }

        $text = trim((string) $slides[$index]);

        return $text !== '' ? $text : null;
    }
}
