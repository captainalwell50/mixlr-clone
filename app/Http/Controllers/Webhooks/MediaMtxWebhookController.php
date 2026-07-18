<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Recording;
use App\Models\Stream;
use App\Services\EventBroadcastService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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

        $prefix = 'live/';
        if (! str_starts_with($validated['path'], $prefix)) {
            return response()->noContent();
        }

        $uuid = substr($validated['path'], strlen($prefix));
        $stream = Stream::query()->where('uuid', $uuid)->first();
        if ($stream === null) {
            return response()->noContent();
        }

        $linked = Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Scheduled, EventStatus::Live])
            ->latest('id')
            ->first();

        if ($event === 'ready') {
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

        $stream->status = StreamStatus::Offline;
        $stream->ended_at = now();
        $stream->save();

        if ($linked && $linked->status === EventStatus::Live) {
            $this->broadcast->markEnded($linked);
        }

        return response()->noContent();
    }

    private function recordingSegment(Request $request): Response
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'segment_relative' => ['required', 'string', 'max:2048'],
            'duration_raw' => ['nullable', 'string', 'max:64'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $prefix = 'live/';
        if (! str_starts_with($validated['path'], $prefix)) {
            return response()->noContent();
        }

        $uuid = substr($validated['path'], strlen($prefix));
        $stream = Stream::query()->where('uuid', $uuid)->first();
        if ($stream === null) {
            return response()->noContent();
        }

        $recording = Recording::query()->updateOrCreate(
            [
                'stream_id' => $stream->id,
                'relative_path' => $validated['segment_relative'],
            ],
            [
                'duration_raw' => $validated['duration_raw'],
                'size_bytes' => $validated['size_bytes'],
                'completed_at' => now(),
            ],
        );

        if ($recording->size_bytes === null && Storage::disk('mediamtx_recordings')->exists($validated['segment_relative'])) {
            $recording->size_bytes = Storage::disk('mediamtx_recordings')->size($validated['segment_relative']);
            $recording->save();
        }

        $stream->archive_path = $validated['segment_relative'];
        $stream->save();

        return response()->noContent();
    }
}
