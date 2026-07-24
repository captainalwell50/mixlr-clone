<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Jobs\SyncRecordingToObjectStorage;
use App\Models\Event;
use App\Models\Recording;
use App\Models\Stream;
use App\Services\RecordingStorageService;
use App\Services\StorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class RecordingController extends Controller
{
    public function store(
        Request $request,
        Stream $stream,
        StorageQuotaService $quota,
        RecordingStorageService $storage,
    ): JsonResponse {
        $this->authorizeManage($request, $stream);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:512000', 'mimetypes:audio/webm,audio/mp4,audio/mpeg,video/webm,video/mp4'],
            'duration_seconds' => ['nullable', 'numeric', 'min:1', 'max:86400'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['audio'];
        $size = (int) ($file->getSize() ?: 0);
        $org = $stream->organization;
        abort_unless($org, 404);

        $quota->assertCanStore($org, $size);

        $maxRecordings = (int) $org->planLimit('max_recordings', 0);
        if ($maxRecordings > 0) {
            $used = Recording::query()
                ->whereIn('stream_id', $org->streams()->pluck('id'))
                ->where('is_public', true)
                ->count();
            if ($used >= $maxRecordings) {
                abort(402, 'Podcast limit reached for this plan. Delete older podcasts or upgrade.');
            }
        }

        $event = $this->resolveEventForUpload($stream, $validated['event_id'] ?? null);

        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['webm', 'mp4', 'm4a', 'mp3'], true)) {
            $mime = (string) ($file->getMimeType() ?: '');
            $ext = str_contains($mime, 'mp4') || str_contains($mime, 'm4a') ? 'mp4' : 'webm';
        }

        $relative = $stream->mediaPath().'/studio_'.now()->format('Y-m-d_H-i-s').'_'.Str::lower(Str::random(6)).'.'.$ext;
        $disk = $storage->localDisk();
        $disk->put($relative, $file->get());

        $duration = isset($validated['duration_seconds'])
            ? (string) round((float) $validated['duration_seconds'], 1)
            : null;

        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $title = $event?->title
                ?: ('Live · '.now()->timezone(config('app.timezone'))->format('M j, g:i A'));
        }

        $recording = Recording::query()->create([
            'stream_id' => $stream->id,
            'event_id' => $event?->id,
            'title' => $title,
            'source' => Recording::SOURCE_STUDIO,
            'is_public' => true,
            'relative_path' => $relative,
            'duration_raw' => $duration,
            'size_bytes' => $size ?: null,
            'completed_at' => now(),
        ]);

        // Studio upload is the preferred archive — hide auto MediaMTX copies for this event.
        if ($event) {
            Recording::query()
                ->where('event_id', $event->id)
                ->where('source', Recording::SOURCE_MEDIAMTX)
                ->where('is_public', true)
                ->update(['is_public' => false]);
        }

        $stream->archive_path = $relative;
        $stream->save();

        if (config('object_storage.enabled')) {
            SyncRecordingToObjectStorage::dispatch($recording->id);
        }

        return response()->json([
            'ok' => true,
            'recording' => $this->recordingPayload($stream, $recording),
        ], 201);
    }

    public function update(Request $request, Stream $stream, Recording $recording): JsonResponse
    {
        abort_unless($recording->stream_id === $stream->id, 404);
        $this->authorizeManage($request, $stream);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $recording->title = trim($validated['title']);
        $recording->save();

        return response()->json([
            'ok' => true,
            'recording' => $this->recordingPayload($stream, $recording->fresh(['event', 'stream'])),
        ]);
    }

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

    /** @return array<string, mixed> */
    private function recordingPayload(Stream $stream, Recording $recording): array
    {
        $recording->loadMissing('event', 'stream');

        return [
            'id' => $recording->id,
            'title' => $recording->displayTitle(),
            'when' => $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y · g:i A'),
            'meta' => $recording->durationLabel().' · '.$recording->sizeLabel(),
            'play_url' => route('archive.play', $recording),
            'update_url' => URL::temporarySignedRoute(
                'recordings.update',
                now()->addHours(12),
                ['stream' => $stream, 'recording' => $recording],
            ),
            'delete_url' => URL::temporarySignedRoute(
                'recordings.destroy',
                now()->addHours(12),
                ['stream' => $stream, 'recording' => $recording],
            ),
        ];
    }

    private function resolveEventForUpload(Stream $stream, mixed $eventId): ?Event
    {
        if ($eventId) {
            $event = Event::query()->find($eventId);
            if ($event && $event->stream_id === $stream->id) {
                return $event;
            }
        }

        return Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Live, EventStatus::Paused, EventStatus::Ended])
            ->latest('id')
            ->first();
    }
}
