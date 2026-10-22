<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Services\RecordingStorageService;
use App\Support\RecordingAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingPlayController extends Controller
{
    public function show(Request $request, Recording $recording, RecordingStorageService $storage): View
    {
        $recording->loadMissing('stream.organization', 'event');
        abort_unless(RecordingAccess::canListen($request->user(), $recording), 404);

        $stream = $recording->stream;

        return view('archive-play', [
            'recording' => $recording,
            'stream' => $stream,
            'organization' => $stream->organization,
            'fileUrl' => $this->fileUrl($recording, $storage),
            'galleryImages' => $recording->event_id
                ? $stream->serviceGalleryImages($recording->event_id)->limit(24)->get()
                : collect(),
            'listenBackgroundUrl' => $stream->listenBackgroundUrl(),
        ]);
    }

    public function file(Request $request, Recording $recording, RecordingStorageService $storage): StreamedResponse|Response|RedirectResponse|BinaryFileResponse
    {
        $recording->loadMissing('stream.organization');
        $this->authorizeFile($request, $recording);

        $url = $storage->temporaryUrl($recording);
        if (is_string($url) && $url !== '') {
            return redirect()->away($url);
        }

        return $storage->streamResponse($recording);
    }

    /**
     * The file route does not start a session, so a manager is not logged in here.
     * The player page issues a short-lived signed URL after canListen allows them.
     * A public recording stays on the plain file URL.
     */
    private function authorizeFile(Request $request, Recording $recording): void
    {
        if (RecordingAccess::canListen(null, $recording)) {
            return;
        }

        abort_unless($request->hasValidSignature(), 404);
    }

    private function fileUrl(Recording $recording, RecordingStorageService $storage): string
    {
        if (! $storage->exists($recording)) {
            return '';
        }

        if (RecordingAccess::canListen(null, $recording)) {
            return route('archive.file', $recording);
        }

        return URL::temporarySignedRoute(
            'archive.file',
            now()->addHours(2),
            ['recording' => $recording],
        );
    }
}
