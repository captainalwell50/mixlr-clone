<?php

namespace App\Http\Controllers;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Models\Stream;
use Illuminate\Support\Facades\URL;

class StudioController extends Controller
{
    public function show(Stream $stream)
    {
        $stream->loadMissing('organization');

        $openEvent = $stream->openServiceEvent();
        $liveOrPaused = $openEvent && in_array($openEvent->status, [EventStatus::Live, EventStatus::Paused], true)
            ? $openEvent
            : $stream->events()
                ->whereIn('status', [EventStatus::Live, EventStatus::Paused])
                ->latest('id')
                ->first();

        $listenUrl = $liveOrPaused
            ? route('events.show', $liveOrPaused)
            : route('listen.stream', $stream);

        $channelUrl = $stream->organization
            ? $stream->organization->channelUrl()
            : $listenUrl;

        $galleryListUrl = $openEvent
            ? route('gallery.index', ['stream' => $stream, 'event_id' => $openEvent->id])
            : route('gallery.index', $stream);

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
            'galleryListUrl' => $galleryListUrl,
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
            'recordingUploadUrl' => URL::temporarySignedRoute(
                'recordings.store',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'sessionShowUrl' => URL::temporarySignedRoute(
                'studio.session.show',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'sessionCreateEventUrl' => URL::temporarySignedRoute(
                'studio.session.create-event',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'sessionGoLiveUrl' => URL::temporarySignedRoute(
                'studio.session.go-live',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'sessionPauseUrl' => URL::temporarySignedRoute(
                'studio.session.pause',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'sessionResumeUrl' => URL::temporarySignedRoute(
                'studio.session.resume',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'sessionEndUrl' => URL::temporarySignedRoute(
                'studio.session.end',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'sessionRenameEventUrl' => URL::temporarySignedRoute(
                'studio.session.rename-event',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'scriptureEnabled' => $stream->organization?->creator_type === CreatorType::Church,
            'scriptureShowUrl' => URL::temporarySignedRoute(
                'studio.scripture.show',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'scriptureStoreUrl' => URL::temporarySignedRoute(
                'studio.scripture.store',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'scriptureDestroyUrl' => URL::temporarySignedRoute(
                'studio.scripture.destroy',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'scriptureSuggestUrl' => URL::temporarySignedRoute(
                'studio.scripture.suggest',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'listenBackgroundUrl' => $stream->listenBackgroundUrl(),
            'recordings' => $stream->recordings()
                ->with('event')
                ->where('is_public', true)
                ->latest('completed_at')
                ->limit(30)
                ->get(),
            'openEvent' => $openEvent,
            'galleryImages' => $openEvent
                ? $stream->serviceGalleryImages($openEvent->id)->limit(20)->get()
                : collect(),
        ]);
    }
}
