<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Stream;
use App\Services\VideoReel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GalleryController extends Controller
{
    public function index(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);

        $eventId = $this->requestedEventId($request);

        $images = $stream->serviceGalleryImages($eventId)
            ->limit(40)
            ->get()
            ->map(fn (GalleryImage $image) => $image->toGalleryPayload());

        return response()->json([
            'images' => $images,
            'event_id' => $stream->resolveGalleryEventId($eventId),
        ]);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeUpload($request, $stream);
        $stream->organization?->assertFeature('gallery', 'Upgrade your plan to use the live gallery.');

        $eventId = $this->resolveUploadEventId($request, $stream);

        if ($request->hasFile('video')) {
            return $this->storeVideoReel($request, $stream, $eventId);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $validated['image']->store('gallery/'.$stream->uuid, 'public');

        $image = GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $eventId,
            'uploaded_by' => $request->user()?->id,
            'path' => $path,
            'media_type' => 'image',
            'caption' => $validated['caption'] ?? null,
            'sort_order' => 0,
        ]);

        return response()->json([
            'image' => $image->toGalleryPayload(),
        ], 201);
    }

    public function destroy(Request $request, Stream $stream, GalleryImage $image): JsonResponse
    {
        $this->authorizeUpload($request, $stream);
        abort_unless($image->stream_id === $stream->id, 404);

        Storage::disk('public')->delete($image->path);
        if (is_string($image->poster_path) && $image->poster_path !== '') {
            Storage::disk('public')->delete($image->poster_path);
        }
        $image->delete();

        return response()->json(['ok' => true]);
    }

    public function destroySelected(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeUpload($request, $stream);

        $validated = $request->validate([
            'image_id' => ['required', 'integer'],
        ]);

        $image = GalleryImage::query()
            ->where('stream_id', $stream->id)
            ->whereKey($validated['image_id'])
            ->firstOrFail();

        return $this->destroy($request, $stream, $image);
    }

    public function storeBackground(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeUpload($request, $stream);

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:12288'],
        ]);

        $path = $validated['image']->store('listen-bg/'.$stream->uuid, 'public');

        $previous = $stream->listen_background_path;
        if (is_string($previous) && $previous !== '' && ! str_starts_with($previous, 'http')) {
            Storage::disk('public')->delete($previous);
        }

        $stream->forceFill(['listen_background_path' => $path])->save();

        return response()->json([
            'background_url' => $stream->listenBackgroundUrl(),
        ]);
    }

    private function storeVideoReel(Request $request, Stream $stream, int $eventId): JsonResponse
    {
        $validated = $request->validate([
            'video' => ['required', 'file', 'max:51200', 'mimetypes:video/mp4,video/webm,video/quicktime'],
            'caption' => ['nullable', 'string', 'max:500'],
            'duration_seconds' => ['nullable', 'numeric', 'min:1', 'max:'.VideoReel::MAX_DURATION_SECONDS],
        ]);

        $reel = app(VideoReel::class)->store(
            $stream,
            $validated['video'],
            $validated['caption'] ?? null,
            isset($validated['duration_seconds']) ? (float) $validated['duration_seconds'] : null,
            $request->user()?->id,
            $eventId,
        );

        return response()->json([
            'image' => $reel->toGalleryPayload(),
        ], 201);
    }

    private function requestedEventId(Request $request): ?int
    {
        $raw = $request->query('event_id', $request->input('event_id'));
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    /**
     * Attach uploads to an open service event (or an explicit event on this stream).
     *
     * @throws ValidationException
     */
    private function resolveUploadEventId(Request $request, Stream $stream): int
    {
        $requested = $this->requestedEventId($request);

        if ($requested !== null) {
            $event = $stream->events()->whereKey($requested)->first();
            if ($event === null) {
                throw ValidationException::withMessages([
                    'event_id' => 'That event does not belong to this channel.',
                ]);
            }

            return (int) $event->id;
        }

        $open = $stream->openServiceEvent();
        if ($open === null) {
            throw ValidationException::withMessages([
                'event_id' => 'Create or go live to an event before posting to the service gallery.',
            ]);
        }

        return (int) $open->id;
    }

    private function authorizeListen(Request $request, Stream $stream): void
    {
        $organization = $stream->organization;
        $user = $request->user();

        abort_unless(
            ($stream->is_public && ($organization?->is_public ?? false))
            || $user?->canManageOrganization($organization)
            || $user?->canManageStream($stream),
            404
        );
    }

    private function authorizeUpload(Request $request, Stream $stream): void
    {
        $user = $request->user();
        $canManage = $user?->canManageOrganization($stream->organization)
            || $user?->canManageStream($stream);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }
}
