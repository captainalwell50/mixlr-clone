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

        $channelUrl = $stream->organization
            ? route('channels.show', $stream->organization)
            : $listenUrl;

        return view('studio', [
            'stream' => $stream,
            'whipUrl' => $stream->whipUrl(),
            'organization' => $stream->organization,
            'listenUrl' => $listenUrl,
            'channelUrl' => $channelUrl,
            'broadcastAllowed' => $stream->organization?->allowsBroadcast() ?? true,
            'billingUrl' => route('billing.plans'),
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
            'libraryListUrl' => URL::temporarySignedRoute(
                'studio.library.index',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'libraryUploadUrl' => URL::temporarySignedRoute(
                'studio.library.store',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'libraryImportDriveUrl' => URL::temporarySignedRoute(
                'studio.library.import-drive',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'galleryImages' => $stream->galleryImages()->limit(20)->get(),
            'listenBackgroundUrl' => $stream->listenBackgroundUrl(),
            'recordings' => $stream->recordings()->latest('completed_at')->limit(30)->get(),
        ]);
    }
}
