<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Services\RecordingStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingPlayController extends Controller
{
    public function show(Recording $recording): View
    {
        $recording->loadMissing('stream.organization', 'event');
        $stream = $recording->stream;

        return view('archive-play', [
            'recording' => $recording,
            'stream' => $stream,
            'organization' => $stream->organization,
            'fileUrl' => route('archive.file', $recording),
            'galleryImages' => $recording->event_id
                ? $stream->serviceGalleryImages($recording->event_id)->limit(24)->get()
                : collect(),
            'listenBackgroundUrl' => $stream->listenBackgroundUrl(),
        ]);
    }

    public function file(Recording $recording, RecordingStorageService $storage): StreamedResponse|Response|RedirectResponse
    {
        $url = $storage->temporaryUrl($recording);
        if (is_string($url) && $url !== '') {
            return redirect()->away($url);
        }

        return $storage->streamResponse($recording);
    }
}
