<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncRecordingToObjectStorage;
use App\Models\Event;
use App\Models\Recording;
use App\Models\Stream;
use App\Services\EventBroadcastService;
use App\Support\MediaMtxDuration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaMtxWebhookController extends Controller
{
    public function __construct(private EventBroadcastService $broadcast) {}

    public function __invoke(Request $request): Response
    {
        $secret = config('streaming.mediamtx.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Webhook not configured.');
        }

        if ($request->bearerToken() !== $secret) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $base = $request->validate([
            'event' => ['required', 'string', Rule::in(['ready', 'not_ready', 'record_segment_complete'])],
        ]);

        return match ($base['event']) {
            'ready', 'not_ready' => $this->presence($request, $base['event']),
            'record_segment_complete' => $this->recordingSegment($request),
        };
    }

    private function presence(Request $request, string $event): Response
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        // Ignore AAC sidecar ready/not_ready — presence tracks the primary publisher only.
        $uuid = $this->primaryLiveUuid($validated['path']);
        if ($uuid === null) {
            return response()->noContent();
        }

        $stream = Stream::query()->where('uuid', $uuid)->first();
        if ($stream === null) {
            return response()->noContent();
        }

        // Open session = scheduled / live / paused. Ended events stay closed;
        // the next go-live creates a new event explicitly.
        $linked = Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Scheduled, EventStatus::Live, EventStatus::Paused])
            ->latest('id')
            ->first();

        if ($event === 'ready') {
            // Cancel any pending disconnect finalize from a refresh / brief drop.
            Cache::forget($this->goneTokenCacheKey($uuid));
            // Stamp before save so a racing not_ready can detect the replace.
            Cache::put($this->lastReadyCacheKey($uuid), microtime(true), now()->addDay());

            $stream->status = StreamStatus::Live;
            if ($stream->started_at === null) {
                $stream->started_at = now();
            }
            $stream->ended_at = null;
            $stream->save();

            if ($linked) {
                $this->broadcast->markLive($linked);
            }

            return response()->noContent();
        }

        // MediaMTX publisher replace emits not_ready then ready. Those HTTP callbacks can
        // finish out of order on sync PHP-FPM and leave the stream stuck offline.
        $notReadyStarted = microtime(true);
        $raceMs = max(0, (int) config('streaming.mediamtx.not_ready_race_ms', 500));
        if ($raceMs > 0) {
            usleep($raceMs * 1000);
        }
        $lastReady = (float) Cache::get($this->lastReadyCacheKey($uuid), 0.0);
        if ($lastReady >= $notReadyStarted) {
            return response()->noContent();
        }

        // Browser refresh / WHIP reconnect: keep the event live for a grace period so
        // Studio can republish without looking "ended" to listeners.
        $token = (string) Str::uuid();
        Cache::put($this->goneTokenCacheKey($uuid), $token, now()->addMinutes(10));

        $grace = max(0, (int) config('streaming.mediamtx.publisher_disconnect_grace_seconds', 45));
        $finalize = function () use ($uuid, $token): void {
            $this->finalizePublisherGone($uuid, $token);
        };

        if ($grace <= 0) {
            $finalize();
        } else {
            dispatch(function () use ($finalize, $grace): void {
                sleep($grace);
                $finalize();
            })->afterResponse();
        }

        return response()->noContent();
    }

    private function finalizePublisherGone(string $uuid, string $token): void
    {
        if (Cache::get($this->goneTokenCacheKey($uuid)) !== $token) {
            return;
        }

        Cache::forget($this->goneTokenCacheKey($uuid));

        $stream = Stream::query()->where('uuid', $uuid)->first();
        if ($stream === null) {
            return;
        }

        $stream->status = StreamStatus::Offline;
        $stream->ended_at = now();
        $stream->save();

        $linked = Event::query()
            ->where('stream_id', $stream->id)
            ->where('status', EventStatus::Live)
            ->latest('id')
            ->first();

        // Publisher drop = pause the open event (resume later). Explicit End live closes it.
        if ($linked) {
            $this->broadcast->markPaused($linked);
        }
    }

    private function primaryLiveUuid(string $path): ?string
    {
        if (! preg_match(
            '/^live\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i',
            $path,
            $matches
        )) {
            return null;
        }

        return strtolower($matches[1]);
    }

    private function lastReadyCacheKey(string $uuid): string
    {
        return 'mediamtx:last_ready:'.$uuid;
    }

    private function goneTokenCacheKey(string $uuid): string
    {
        return 'mediamtx:publisher_gone:'.$uuid;
    }

    private function recordingSegment(Request $request): Response
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'segment_relative' => ['required', 'string', 'max:2048'],
            'duration_raw' => ['nullable', 'string', 'max:64'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $uuid = $this->primaryLiveUuid($validated['path']);
        if ($uuid === null) {
            return response()->noContent();
        }

        $stream = Stream::query()->where('uuid', $uuid)->first();
        if ($stream === null) {
            return response()->noContent();
        }

        $durationSeconds = MediaMtxDuration::toSeconds($validated['duration_raw'] ?? null);
        $sizeBytes = isset($validated['size_bytes']) ? (int) $validated['size_bytes'] : null;
        if ($sizeBytes === null && Storage::disk('mediamtx_recordings')->exists($validated['segment_relative'])) {
            $sizeBytes = Storage::disk('mediamtx_recordings')->size($validated['segment_relative']);
        }

        $minSeconds = max(0, (int) config('streaming.mediamtx.archive_min_segment_seconds', 60));
        $minBytes = max(0, (int) config('streaming.mediamtx.archive_min_segment_bytes', 80_000));

        // Drop reconnect blips that previously polluted Podcasts.
        if ($durationSeconds !== null && $durationSeconds < $minSeconds) {
            return response()->noContent();
        }
        if ($durationSeconds === null && ($sizeBytes === null || $sizeBytes < $minBytes)) {
            return response()->noContent();
        }

        $event = Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Live, EventStatus::Paused, EventStatus::Ended])
            ->latest('id')
            ->first();

        // Prefer Studio uploads when present for this event.
        $studioAlreadyPublic = $event !== null && Recording::query()
            ->where('event_id', $event->id)
            ->where('source', Recording::SOURCE_STUDIO)
            ->where('is_public', true)
            ->exists();

        $isPublic = ! $studioAlreadyPublic && ($event === null || $event->status === EventStatus::Ended);

        $title = $event?->title
            ?: ('Live · '.now()->timezone(config('app.timezone'))->format('M j, g:i A'));

        $recording = Recording::query()->updateOrCreate(
            [
                'stream_id' => $stream->id,
                'relative_path' => $validated['segment_relative'],
            ],
            [
                'event_id' => $event?->id,
                'title' => $title,
                'source' => Recording::SOURCE_MEDIAMTX,
                'is_public' => $isPublic,
                'duration_raw' => $durationSeconds !== null
                    ? (string) round($durationSeconds, 1)
                    : ($validated['duration_raw'] ?? null),
                'size_bytes' => $sizeBytes,
                'completed_at' => now(),
            ],
        );

        $stream->archive_path = $validated['segment_relative'];
        $stream->save();

        if ($isPublic && config('object_storage.enabled')) {
            SyncRecordingToObjectStorage::dispatch($recording->id);
        }

        return response()->noContent();
    }
}
