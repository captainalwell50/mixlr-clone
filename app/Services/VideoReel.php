<?php

namespace App\Services;

use App\Models\GalleryImage;
use App\Models\Stream;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class VideoReel
{
    public const MAX_DURATION_SECONDS = 60;

    public const MAX_BYTES = 50 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    /**
     * Store a short vertical/service video reel for the listener gallery.
     *
     * @throws ValidationException
     */
    public function store(Stream $stream, UploadedFile $video, ?string $caption = null, ?float $durationSeconds = null, ?int $uploadedBy = null): GalleryImage
    {
        if ($video->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'video' => 'Video reel must be 50 MB or smaller.',
            ]);
        }

        $mime = $video->getMimeType() ?: '';
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'video' => 'Use an MP4, WebM, or MOV video reel.',
            ]);
        }

        $duration = $durationSeconds !== null ? (int) round($durationSeconds) : null;
        if ($duration !== null && ($duration < 1 || $duration > self::MAX_DURATION_SECONDS)) {
            throw ValidationException::withMessages([
                'video' => 'Video reels must be between 1 and 60 seconds (30s or 1 min clips work best).',
            ]);
        }

        $path = $video->store('gallery/'.$stream->uuid.'/reels', 'public');

        return GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $stream->events()->where('status', 'live')->latest('id')->value('id'),
            'uploaded_by' => $uploadedBy,
            'path' => $path,
            'media_type' => 'video',
            'duration_seconds' => $duration,
            'caption' => $caption,
            'sort_order' => 0,
        ]);
    }
}
