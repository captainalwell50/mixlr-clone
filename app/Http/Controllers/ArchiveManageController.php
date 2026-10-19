<?php

namespace App\Http\Controllers;

use App\Contracts\AudioSegmentConcatenator;
use App\Models\Recording;
use App\Models\Stream;
use App\Services\RecordingStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveManageController extends Controller
{
    public function publish(Request $request, Recording $recording): JsonResponse|RedirectResponse
    {
        $recording->loadMissing('stream.organization');
        $stream = $recording->stream;
        abort_unless($stream !== null, 404);
        $this->authorizeManage($request, $stream);

        $published = $request->boolean('published');
        $recording->is_public = $published;
        $recording->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'published' => $recording->is_public,
            ]);
        }

        return back()->with('status', $published
            ? 'Podcast published.'
            : 'Podcast unpublished. The recording stays saved.');
    }

    public function edit(Request $request, Recording $recording, RecordingStorageService $storage): View
    {
        $recording->loadMissing('stream.organization', 'event');
        $stream = $recording->stream;
        abort_unless($stream !== null, 404);
        abort_unless($request->user()?->canManageStream($stream), 403);

        $source = $recording->master_path ?: $recording->relative_path;
        $duration = $recording->master_duration_raw
            ? (float) $recording->master_duration_raw
            : $recording->durationSeconds();

        return view('archive-edit', [
            'recording' => $recording,
            'stream' => $stream,
            'organization' => $stream->organization,
            'fileUrl' => $storage->localDisk()->exists($source) ? route('archive.source', $recording) : '',
            'durationSeconds' => $duration,
            'trimStart' => $recording->trim_start_seconds,
            'trimEnd' => $recording->trim_end_seconds,
        ]);
    }

    public function trim(
        Request $request,
        Recording $recording,
        RecordingStorageService $storage,
        AudioSegmentConcatenator $audio,
    ): RedirectResponse {
        $recording->loadMissing('stream');
        $stream = $recording->stream;
        abort_unless($stream !== null, 404);
        abort_unless($request->user()?->canManageStream($stream), 403);

        $fullDuration = $recording->master_duration_raw
            ? (float) $recording->master_duration_raw
            : $recording->durationSeconds();

        $validated = $request->validate([
            'start_seconds' => ['required', 'numeric', 'min:0'],
            'end_seconds' => ['required', 'numeric', 'gt:start_seconds'],
        ]);

        $start = round((float) $validated['start_seconds'], 3);
        $end = round((float) $validated['end_seconds'], 3);
        if (($end - $start) < 1) {
            return back()->withErrors(['end_seconds' => 'Keep at least one second of audio.']);
        }
        if ($fullDuration !== null && $end > $fullDuration + 0.5) {
            return back()->withErrors(['end_seconds' => 'The end point is past the end of the recording.']);
        }

        $master = $recording->master_path ?: $recording->relative_path;
        $input = $storage->localAbsolutePath($master);
        if (! is_readable($input)) {
            return back()->withErrors(['start_seconds' => 'The full recording file is not on the server.']);
        }

        $relative = $stream->mediaPath().'/trim_'.$recording->id.'_'.Str::lower(Str::random(6)).'.mp4';
        $output = $storage->localAbsolutePath($relative);
        if (! $audio->trim($input, $output, $start, $end)) {
            return back()->withErrors(['start_seconds' => 'Could not trim that selection.']);
        }

        $previous = $recording->relative_path;
        $size = (int) filesize($output);

        $recording->forceFill([
            'master_path' => $master,
            'master_duration_raw' => $recording->master_duration_raw ?: $recording->duration_raw,
            'relative_path' => $relative,
            'duration_raw' => (string) round($end - $start, 1),
            'size_bytes' => $size,
            'trim_start_seconds' => $start,
            'trim_end_seconds' => $end,
            'object_key' => null,
            'synced_at' => null,
        ])->save();

        if ($previous !== $master && $previous !== $relative) {
            try {
                $storage->localDisk()->delete($previous);
            } catch (\Throwable) {
                //
            }
        }

        return redirect()
            ->route('archive.edit', $recording)
            ->with('status', 'Trim saved. Publish when this cut is the one you want on the podcast list.');
    }

    public function restore(Request $request, Recording $recording, RecordingStorageService $storage): RedirectResponse
    {
        $recording->loadMissing('stream');
        $stream = $recording->stream;
        abort_unless($stream !== null && $request->user()?->canManageStream($stream), 403);
        abort_unless(filled($recording->master_path), 404);

        $trimmed = $recording->relative_path;
        $master = $recording->master_path;
        $size = null;
        try {
            $size = (int) $storage->localDisk()->size($master);
        } catch (\Throwable) {
            $size = $recording->size_bytes;
        }

        $recording->forceFill([
            'relative_path' => $master,
            'duration_raw' => $recording->master_duration_raw ?: $recording->duration_raw,
            'size_bytes' => $size,
            'master_path' => null,
            'master_duration_raw' => null,
            'trim_start_seconds' => null,
            'trim_end_seconds' => null,
            'object_key' => null,
            'synced_at' => null,
        ])->save();

        if ($trimmed !== $master) {
            try {
                $storage->localDisk()->delete($trimmed);
            } catch (\Throwable) {
                //
            }
        }

        return redirect()
            ->route('archive.edit', $recording)
            ->with('status', 'Restored the full recording.');
    }

    private function authorizeManage(Request $request, Stream $stream): void
    {
        $canManage = $request->user()?->canManageStream($stream);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }

    public function source(Request $request, Recording $recording, RecordingStorageService $storage): BinaryFileResponse|StreamedResponse
    {
        $recording->loadMissing('stream');
        $stream = $recording->stream;
        abort_unless($stream !== null && $request->user()?->canManageStream($stream), 403);

        $relative = $recording->master_path ?: $recording->relative_path;
        $playback = $recording->replicate();
        $playback->relative_path = $relative;
        $playback->object_key = null;
        $playback->synced_at = null;
        $playback->local_deleted_at = null;

        return $storage->streamResponse($playback);
    }
}
