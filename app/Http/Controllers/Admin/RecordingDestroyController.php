<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordingDestroyController extends Controller
{
    public function __invoke(Request $request, Recording $recording): RedirectResponse
    {
        $recording->loadMissing('stream.organization');

        if (! $request->user()?->canManageStream($recording->stream)) {
            abort(403);
        }

        $disk = Storage::disk('mediamtx_recordings');
        if (is_string($recording->relative_path) && $recording->relative_path !== '' && $disk->exists($recording->relative_path)) {
            $disk->delete($recording->relative_path);
        }

        $recording->delete();

        return back()->with('status', __('Recording deleted.'));
    }
}
