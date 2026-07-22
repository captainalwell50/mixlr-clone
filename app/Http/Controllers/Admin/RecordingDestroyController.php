<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Services\RecordingStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecordingDestroyController extends Controller
{
    public function __invoke(Request $request, Recording $recording, RecordingStorageService $storage): RedirectResponse
    {
        $recording->loadMissing('stream.organization');

        if (! $request->user()?->canManageStream($recording->stream)) {
            abort(403);
        }

        $storage->deleteFiles($recording);
        $recording->delete();

        return back()->with('status', __('Recording deleted.'));
    }
}
