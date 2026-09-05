<?php

namespace App\Http\Controllers;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Models\Stream;
use App\Support\StudioExpiry;
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

        $channelUrl = $stream->organization
            ? $stream->organization->channelUrl()
            : ($liveOrPaused
                ? route('events.show', $liveOrPaused)
                : route('listen.stream', $stream));

        $galleryListUrl = $openEvent
            ? route('gallery.index', ['stream' => $stream, 'event_id' => $openEvent->id])
            : route('gallery.index', $stream);

        return view('studio', [
            'stream' => $stream,
            'whipUrl' => $stream->whipUrl(),
            'organization' => $stream->organization,
            'channelUrl' => $channelUrl,
            'broadcastAllowed' => $stream->organization?->allowsBroadcast() ?? true,
            'billingUrl' => route('billing.plans'),
            'galleryUploadUrl' => $this->signedStudioRoute('gallery.store', $stream),
            'galleryDestroyUrl' => $this->signedStudioRoute('studio.gallery.destroy', $stream),
            'galleryListUrl' => $galleryListUrl,
            'libraryListUrl' => $this->signedStudioRoute('studio.library.index', $stream),
            'libraryUploadUrl' => $this->signedStudioRoute('studio.library.store', $stream),
            'libraryImportDriveUrl' => $this->signedStudioRoute('studio.library.import-drive', $stream),
            'recordingUploadUrl' => $this->signedStudioRoute('recordings.store', $stream),
            'sessionShowUrl' => $this->signedStudioRoute('studio.session.show', $stream),
            'sessionCreateEventUrl' => $this->signedStudioRoute('studio.session.create-event', $stream),
            'sessionGoLiveUrl' => $this->signedStudioRoute('studio.session.go-live', $stream),
            'sessionPauseUrl' => $this->signedStudioRoute('studio.session.pause', $stream),
            'sessionResumeUrl' => $this->signedStudioRoute('studio.session.resume', $stream),
            'sessionEndUrl' => $this->signedStudioRoute('studio.session.end', $stream),
            'sessionRenameEventUrl' => $this->signedStudioRoute('studio.session.rename-event', $stream),
            'scriptureEnabled' => $stream->organization?->creator_type === CreatorType::Church,
            'scriptureShowUrl' => $this->signedStudioRoute('studio.scripture.show', $stream),
            'scriptureStoreUrl' => $this->signedStudioRoute('studio.scripture.store', $stream),
            'scriptureDestroyUrl' => $this->signedStudioRoute('studio.scripture.destroy', $stream),
            'scriptureSuggestUrl' => $this->signedStudioRoute('studio.scripture.suggest', $stream),
            'songsIndexUrl' => $this->signedStudioRoute('studio.songs.index', $stream),
            'songsStoreUrl' => $this->signedStudioRoute('studio.songs.store', $stream),
            'songsCueClearUrl' => $this->signedStudioRoute('studio.songs.clear', $stream),
            'songsCueNextUrl' => $this->signedStudioRoute('studio.songs.next', $stream),
            'songsCuePreviousUrl' => $this->signedStudioRoute('studio.songs.previous', $stream),
            'songsShowUrl' => $this->signedStudioRoute('studio.songs.show', $stream),
            'openEvent' => $openEvent,
            'galleryImages' => $openEvent
                ? $stream->serviceGalleryImages($openEvent->id)->limit(20)->get()
                : collect(),
        ]);
    }

    private function signedStudioRoute(string $name, Stream $stream): string
    {
        return URL::temporarySignedRoute($name, StudioExpiry::at(), ['stream' => $stream]);
    }
}
