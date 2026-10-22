<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Recording;
use App\Models\SiteSetting;
use App\Models\Stream;
use App\Services\EventBroadcastService;
use App\Support\PublisherClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioSessionController extends Controller
{
    public function __construct(private EventBroadcastService $broadcast) {}

    public function show(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $open = $this->broadcast->openEventForStream($stream);

        return response()->json([
            'stream_id' => $stream->id,
            'event' => $open ? $this->broadcast->eventPayload($open) : null,
            'csrf' => csrf_token(),
            'reload_at' => SiteSetting::studioReloadAt($stream->uuid),
            'opus_send_bitrate' => SiteSetting::opusSendBitrate(),
        ]);
    }

    public function createEvent(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);
        abort_unless($stream->organization?->allowsBroadcast() ?? false, 402);

        $open = $this->broadcast->openEventForStream($stream);
        if ($open && in_array($open->status, [EventStatus::Live, EventStatus::Paused], true)) {
            return response()->json([
                'message' => 'Finish or end the current event before creating a new one.',
                'event' => $this->broadcast->eventPayload($open),
            ], 422);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Replace unused scheduled placeholder with the named event.
        if ($open && $open->status === EventStatus::Scheduled) {
            $open->title = $validated['title'];
            $open->description = $validated['description'] ?? $open->description;
            $open->save();
            $event = $open;
        } else {
            $event = $this->broadcast->createEventOnStream(
                $stream,
                $validated['title'],
                $validated['description'] ?? null,
            );
        }

        return response()->json([
            'ok' => true,
            'event' => $this->broadcast->eventPayload($event),
        ], 201);
    }

    public function renameEvent(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        $event = null;
        if (! empty($validated['event_id'])) {
            $event = Event::query()->find($validated['event_id']);
            abort_unless($event && $event->stream_id === $stream->id, 404);
        } else {
            $event = $this->broadcast->openEventForStream($stream);
        }

        abort_unless($event !== null, 422, 'No event to rename. Create an event or go live first.');

        $previousTitle = $event->title;
        $event->title = trim($validated['title']);
        $event->save();

        // Keep podcasts that still carry the old auto event name in sync.
        \App\Models\Recording::query()
            ->where('event_id', $event->id)
            ->where(function ($q) use ($previousTitle) {
                $q->whereNull('title')
                    ->orWhere('title', $previousTitle)
                    ->orWhere('title', '');
            })
            ->update(['title' => $event->title]);

        return response()->json([
            'ok' => true,
            'event' => $this->broadcast->eventPayload($event->fresh()),
        ]);
    }

    public function goLive(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);
        abort_unless($stream->organization?->allowsBroadcast() ?? false, 402);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        $event = null;
        if (! empty($validated['event_id'])) {
            $event = Event::query()->findOrFail($validated['event_id']);
        }

        $live = $this->broadcast->goLiveOnStream(
            $stream,
            $validated['title'] ?? null,
            $event,
        );

        return response()->json([
            'ok' => true,
            'event' => $this->broadcast->eventPayload($live),
            'whip_url' => $stream->whipUrl(),
            'opus_send_bitrate' => SiteSetting::opusSendBitrate(),
        ]);
    }

    public function pause(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $open = $this->broadcast->openEventForStream($stream);
        abort_unless($open && $open->status === EventStatus::Live, 422, 'Nothing is live to pause.');

        $this->broadcast->markPaused($open);

        return response()->json([
            'ok' => true,
            'event' => $this->broadcast->eventPayload($open->fresh()),
        ]);
    }

    public function resume(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);
        abort_unless($stream->organization?->allowsBroadcast() ?? false, 402);

        $open = $this->broadcast->openEventForStream($stream);
        abort_unless($open && $open->status === EventStatus::Paused, 422, 'No paused event to resume.');

        $this->broadcast->markLive($open);

        return response()->json([
            'ok' => true,
            'event' => $this->broadcast->eventPayload($open->fresh()),
            'whip_url' => $stream->whipUrl(),
            'opus_send_bitrate' => SiteSetting::opusSendBitrate(),
        ]);
    }

    public function end(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $open = $this->broadcast->openEventForStream($stream);
        abort_unless($open !== null, 422, 'No open event to end.');

        $this->broadcast->markEnded($open);
        PublisherClaim::forget($stream->uuid);

        return response()->json([
            'ok' => true,
            'event' => $this->broadcast->eventPayload($open->fresh()),
        ]);
    }

    public function latestRecording(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
        ]);

        $recording = Recording::query()
            ->where('stream_id', $stream->id)
            ->where('event_id', (int) $validated['event_id'])
            ->whereIn('source', [Recording::SOURCE_STUDIO, Recording::SOURCE_ASSEMBLED])
            ->latest('id')
            ->first();

        return response()->json([
            'recording' => $recording
                ? app(RecordingController::class)->recordingPayload($stream, $recording)
                : null,
        ]);
    }

    public function publisherClaim(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $validated = $request->validate([
            'claim' => ['required', 'uuid'],
            'intent' => ['required', 'in:claim,check,release'],
        ]);

        if ($validated['intent'] === 'claim') {
            PublisherClaim::remember($stream->uuid, $validated['claim']);
        }

        if ($validated['intent'] === 'release'
            && PublisherClaim::current($stream->uuid) === $validated['claim']) {
            PublisherClaim::forget($stream->uuid);
        }

        $owns = PublisherClaim::current($stream->uuid) === $validated['claim'];

        return response()->json([
            'ok' => true,
            'owns' => $owns,
            'replaced' => PublisherClaim::replaced($stream->uuid, $validated['claim']),
        ]);
    }

    private function authorizeStudio(Request $request, Stream $stream): void
    {
        $canManage = $request->user()?->canManageStream($stream);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }
}
