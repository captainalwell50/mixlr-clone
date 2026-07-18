<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingDownloadController extends Controller
{
    public function show(Request $request, Recording $recording): StreamedResponse|Response
    {
        $recording->loadMissing('stream.organization');

        if (! $request->user()?->canManageStream($recording->stream)) {
            abort(403);
        }

        $disk = Storage::disk('mediamtx_recordings');

        if (! $disk->exists($recording->relative_path)) {
            abort(404);
        }

        $filename = basename($recording->relative_path);

        return $disk->download($recording->relative_path, $filename);
    }
}
