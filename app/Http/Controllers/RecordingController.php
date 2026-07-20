<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordingController extends Controller
{
    public function destroy(Request $request, Stream $stream, Recording $recording): JsonResponse|RedirectResponse
    {
        abort_unless($recording->stream_id === $stream->id, 404);
        $this->authorizeManage($request, $stream);

        $disk = Storage::disk('mediamtx_recordings');
        if (is_string($recording->relative_path) && $recording->relative_path !== '' && $disk->exists($recording->relative_path)) {
            $disk->delete($recording->relative_path);
        }

        $recording->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', __('Recording deleted.'));
    }

    private function authorizeManage(Request $request, Stream $stream): void
    {
        $canManage = $request->user()?->canManageStream($stream);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }
}
