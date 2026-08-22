<?php

namespace App\Http\Controllers;

use App\Enums\CreatorType;
use App\Enums\EventStatus;
use App\Models\DisplaySong;
use App\Models\Event;
use App\Models\Stream;
use App\Services\EventBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class SongController extends Controller
{
    public function __construct(
        private EventBroadcastService $broadcast,
    ) {}

    /** Public poll payload fragment — also merged into scripture.show. */
    public function show(Stream $stream): JsonResponse
    {
        if (! $this->isChurch($stream)) {
            return response()->json(['enabled' => false, 'live_board' => null, 'song' => null, 'scripture' => null]);
        }

        $event = $this->openEvent($stream);

        return response()->json([
            'enabled' => true,
            'live_board' => $event?->liveBoardMode(),
            'song' => $this->cuePayload($event),
        ]);
    }

    public function index(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);

        $songs = DisplaySong::query()
            ->where('organization_id', $stream->organization_id)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (DisplaySong $song) => $this->songPayload($song, $stream))
            ->values();

        $event = $this->openEvent($stream);

        return response()->json([
            'songs' => $songs,
            'live_board' => $event?->liveBoardMode(),
            'cue' => $this->cuePayload($event),
        ]);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);

        $validated = $this->validateSong($request);
        $slides = DisplaySong::normalizeSlides($validated['slides'] ?? $validated['body'] ?? null);
        if ($slides === []) {
            return response()->json(['message' => 'Add at least one slide (separate stanzas with a blank line).'], 422);
        }

        $song = DisplaySong::query()->create([
            'organization_id' => $stream->organization_id,
            'title' => $validated['title'],
            'slides' => $slides,
        ]);

        return response()->json([
            'ok' => true,
            'song' => $this->songPayload($song, $stream),
        ], 201);
    }

    public function update(Request $request, Stream $stream, DisplaySong $song): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);
        abort_unless((int) $song->organization_id === (int) $stream->organization_id, 404);

        $validated = $this->validateSong($request);
        $slides = DisplaySong::normalizeSlides($validated['slides'] ?? $validated['body'] ?? null);
        if ($slides === []) {
            return response()->json(['message' => 'Add at least one slide (separate stanzas with a blank line).'], 422);
        }

        $song->forceFill([
            'title' => $validated['title'],
            'slides' => $slides,
        ])->save();

        // Keep live cue in sync only if this song is the last-cued listen board.
        $event = $this->openEvent($stream);
        if ($event && (int) $event->song_id === (int) $song->id && $event->liveBoardMode() === 'song') {
            $index = (int) ($event->song_slide_index ?? 0);
            $index = max(0, min($index, count($slides) - 1));
            $this->applyCue($event, $song, $index);
        }

        return response()->json([
            'ok' => true,
            'song' => $this->songPayload($song->fresh(), $stream),
            'cue' => $this->cuePayload($event?->fresh()),
        ]);
    }

    public function destroy(Request $request, Stream $stream, DisplaySong $song): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);
        abort_unless((int) $song->organization_id === (int) $stream->organization_id, 404);

        $event = $this->openEvent($stream);
        if ($event && (int) $event->song_id === (int) $song->id) {
            $this->clearCue($event);
        }

        $song->delete();

        return response()->json(['ok' => true]);
    }

    public function cue(Request $request, Stream $stream, DisplaySong $song): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);
        abort_unless((int) $song->organization_id === (int) $stream->organization_id, 404);

        $slides = DisplaySong::normalizeSlides($song->slides);
        if ($slides === []) {
            return response()->json(['message' => 'This song has no slides.'], 422);
        }

        $index = (int) $request->input('slide_index', 0);
        $index = max(0, min($index, count($slides) - 1));

        $event = $this->ensureOpenEvent($stream);
        $this->applyCue($event, $song, $index);

        $fresh = $event->fresh();

        return response()->json([
            'ok' => true,
            'live_board' => $fresh?->liveBoardMode(),
            'song' => $this->cuePayload($fresh),
            'scripture' => null,
        ]);
    }

    public function next(Request $request, Stream $stream): JsonResponse
    {
        return $this->nudge($request, $stream, +1);
    }

    public function previous(Request $request, Stream $stream): JsonResponse
    {
        return $this->nudge($request, $stream, -1);
    }

    public function clear(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);

        $event = $this->openEvent($stream);
        if ($event) {
            $this->clearCue($event);
        }

        return response()->json([
            'ok' => true,
            'live_board' => $event?->fresh()?->liveBoardMode(),
            'song' => null,
            'scripture' => $event?->fresh()?->liveScripturePayload(),
        ]);
    }

    private function nudge(Request $request, Stream $stream, int $delta): JsonResponse
    {
        $this->authorizeChurchStudio($request, $stream);

        $event = $this->openEvent($stream);
        if ($event === null || $event->liveBoardMode() !== 'song') {
            return response()->json(['message' => 'No song is live on listen.'], 422);
        }

        $song = $event->song_id
            ? DisplaySong::query()
                ->where('organization_id', $stream->organization_id)
                ->find($event->song_id)
            : null;

        if ($song === null) {
            return response()->json(['message' => 'Live song was removed from the library.'], 422);
        }

        $slides = DisplaySong::normalizeSlides($song->slides);
        if ($slides === []) {
            return response()->json(['message' => 'This song has no slides.'], 422);
        }

        $index = (int) ($event->song_slide_index ?? 0) + $delta;
        $index = max(0, min($index, count($slides) - 1));
        $this->applyCue($event, $song, $index);
        $fresh = $event->fresh();

        return response()->json([
            'ok' => true,
            'live_board' => $fresh?->liveBoardMode(),
            'song' => $this->cuePayload($fresh),
            'scripture' => null,
        ]);
    }

    /**
     * @return array{title: string, slides?: list<string>, body?: string}
     */
    private function validateSong(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slides' => ['sometimes', 'array', 'min:1'],
            'slides.*' => ['string', 'max:4000'],
            'body' => ['sometimes', 'string', 'max:20000'],
        ]);
    }

    private function applyCue(Event $event, DisplaySong $song, int $index): void
    {
        $slides = DisplaySong::normalizeSlides($song->slides);
        $text = $slides[$index] ?? null;
        if ($text === null) {
            return;
        }

        $event->cueLiveSong($song->id, $song->title, $text, $index, count($slides));
    }

    private function clearCue(Event $event): void
    {
        $event->clearLiveSong();
    }

    private function ensureOpenEvent(Stream $stream): Event
    {
        $event = $this->openEvent($stream);
        if ($event !== null) {
            return $event;
        }

        return $this->broadcast->createEventOnStream(
            $stream,
            $this->broadcast->defaultEventTitle($stream),
        );
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
        abort_unless($this->isChurch($stream), 403, 'Song display is only available for church channels.');
    }

    private function openEvent(Stream $stream): ?Event
    {
        return Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Scheduled, EventStatus::Live, EventStatus::Paused])
            ->latest('id')
            ->first();
    }

    /**
     * @return array{id: int, title: string, slides: list<string>, slide_count: int, updated_at: string|null, update_url?: string, destroy_url?: string, cue_url?: string}
     */
    private function songPayload(DisplaySong $song, ?Stream $stream = null): array
    {
        $slides = DisplaySong::normalizeSlides($song->slides);

        $payload = [
            'id' => $song->id,
            'title' => (string) $song->title,
            'slides' => $slides,
            'slide_count' => count($slides),
            'updated_at' => $song->updated_at?->toIso8601String(),
        ];

        if ($stream !== null) {
            $expires = now()->addHours(12);
            $payload['update_url'] = URL::temporarySignedRoute(
                'studio.songs.update',
                $expires,
                ['stream' => $stream, 'song' => $song],
            );
            $payload['destroy_url'] = URL::temporarySignedRoute(
                'studio.songs.destroy',
                $expires,
                ['stream' => $stream, 'song' => $song],
            );
            $payload['cue_url'] = URL::temporarySignedRoute(
                'studio.songs.cue',
                $expires,
                ['stream' => $stream, 'song' => $song],
            );
        }

        return $payload;
    }

    /**
     * @return array{id: int|null, title: string, text: string, slide_index: int, slide_count: int, updated_at: string|null}|null
     */
    public function cuePayload(?Event $event): ?array
    {
        return $event?->liveSongPayload();
    }
}
