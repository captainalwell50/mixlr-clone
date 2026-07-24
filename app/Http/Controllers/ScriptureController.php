<?php

namespace App\Http\Controllers;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Stream;
use App\Services\Bible\KjvBibleService;
use App\Services\EventBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScriptureController extends Controller
{
    public function __construct(
        private KjvBibleService $bible,
        private EventBroadcastService $broadcast,
    ) {}

    /** Public poll for listen portal. */
    public function show(Stream $stream): JsonResponse
    {
        if (! $this->isChurch($stream)) {
            return response()->json(['enabled' => false, 'scripture' => null]);
        }

        $event = $this->openEvent($stream);

        return response()->json([
            'enabled' => true,
            'scripture' => $this->payload($event),
        ]);
    }

    public function suggest(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);

        $q = (string) $request->query('q', '');

        return response()->json([
            'suggestions' => $this->bible->suggest($q),
        ]);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);

        $validated = $request->validate([
            'ref' => ['required', 'string', 'max:120'],
        ]);

        $resolved = $this->bible->resolve($validated['ref']);
        if ($resolved === null) {
            return response()->json([
                'message' => 'Could not find that scripture reference in the KJV.',
            ], 422);
        }

        $event = $this->openEvent($stream);
        if ($event === null) {
            // Auto-create a scheduled placeholder so cues work before go-live.
            $event = $this->broadcast->createEventOnStream(
                $stream,
                $this->broadcast->defaultEventTitle($stream),
            );
        }

        $event->forceFill([
            'scripture_ref' => $resolved['ref'],
            'scripture_text' => $resolved['text'],
            'scripture_updated_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'scripture' => $this->payload($event->fresh()),
        ]);
    }

    public function destroy(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);

        $event = $this->openEvent($stream);
        if ($event) {
            $event->forceFill([
                'scripture_ref' => null,
                'scripture_text' => null,
                'scripture_updated_at' => now(),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'scripture' => null,
        ]);
    }

    private function isChurch(Stream $stream): bool
    {
        $stream->loadMissing('organization');

        return $stream->organization?->creator_type === CreatorType::Church;
    }

    private function authorizeChurchStudio(Request $request, Stream $stream): void
    {
        $canManage = $request->user()?->canManageStream($stream);
        $signed = $request->hasValidSignature();
        abort_unless($canManage || $signed, 403);
        abort_unless($this->isChurch($stream), 403, 'Scripture display is only available for church channels.');
    }

    private function openEvent(Stream $stream): ?Event
    {
        return Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Scheduled, EventStatus::Live, EventStatus::Paused])
            ->latest('id')
            ->first();
    }

    /** @return array{ref: string, text: string, version: string, updated_at: string|null}|null */
    private function payload(?Event $event): ?array
    {
        if ($event === null || ! filled($event->scripture_ref) || ! filled($event->scripture_text)) {
            return null;
        }

        return [
            'ref' => (string) $event->scripture_ref,
            'text' => (string) $event->scripture_text,
            'version' => 'KJV',
            'updated_at' => $event->scripture_updated_at?->toIso8601String(),
        ];
    }
}
