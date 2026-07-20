<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Stream;
use Illuminate\Support\Facades\URL;

class StudioController extends Controller
{
    public function show(Stream $stream)
    {
        $stream->loadMissing('organization');

        $liveEvent = $stream->events()
            ->where('status', EventStatus::Live)
            ->latest('started_at')
            ->first();

        $listenUrl = $liveEvent
            ? route('events.show', $liveEvent)
            : route('listen.stream', $stream);

        return view('studio', [
            'stream' => $stream,
            'whipUrl' => $stream->whipUrl(),
            'organization' => $stream->organization,
            'listenUrl' => $listenUrl,
            'galleryUploadUrl' => URL::temporarySignedRoute(
                'gallery.store',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'backgroundUploadUrl' => URL::temporarySignedRoute(
                'gallery.background',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'galleryListUrl' => route('gallery.index', $stream),
            'galleryImages' => $stream->galleryImages()->limit(20)->get(),
            'listenBackgroundUrl' => $stream->listenBackgroundUrl(),
        ]);
    }
}
