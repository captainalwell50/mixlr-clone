<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Models\Stream;
use App\Services\RecordingStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    public function destroy(
        Request $request,
        Stream $stream,
        Recording $recording,
        RecordingStorageService $storage,
    ): JsonResponse|RedirectResponse {
        abort_unless($recording->stream_id === $stream->id, 404);
        $this->authorizeManage($request, $stream);

        $storage->deleteFiles($recording);
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
