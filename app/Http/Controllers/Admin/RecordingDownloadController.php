<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Services\RecordingStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingDownloadController extends Controller
{
    public function show(Request $request, Recording $recording, RecordingStorageService $storage): StreamedResponse|Response
    {
        $recording->loadMissing('stream.organization');

        if (! $request->user()?->canManageStream($recording->stream)) {
            abort(403);
        }

        return $storage->streamResponse($recording, asDownload: true);
    }
}
