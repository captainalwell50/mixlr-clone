<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingPlayController extends Controller
{
    public function show(Recording $recording): View
    {
        $recording->loadMissing('stream.organization');
        $stream = $recording->stream;

        return view('archive-play', [
            'recording' => $recording,
            'stream' => $stream,
            'fileUrl' => route('archive.file', $recording),
            'galleryImages' => $stream->galleryImages()->limit(24)->get(),
            'listenBackgroundUrl' => $stream->listenBackgroundUrl(),
        ]);
    }

    public function file(Recording $recording): StreamedResponse|Response
    {
        $disk = Storage::disk('mediamtx_recordings');

        if (! $disk->exists($recording->relative_path)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($recording->relative_path, PATHINFO_EXTENSION))) {
            'mp4', 'm4a', 'm4v' => 'audio/mp4',
            'webm' => 'audio/webm',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };

        return $disk->response(
            $recording->relative_path,
            basename($recording->relative_path),
            [
                'Content-Type' => $mime,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }
}
