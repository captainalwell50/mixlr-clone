<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Stream;
use App\Services\VideoReel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function index(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);

        $images = $stream->galleryImages()
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (GalleryImage $image) => $image->toGalleryPayload());

        return response()->json(['images' => $images]);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeUpload($request, $stream);

        if ($request->hasFile('video')) {
            return $this->storeVideoReel($request, $stream);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $validated['image']->store('gallery/'.$stream->uuid, 'public');

        $image = GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $stream->events()->where('status', 'live')->latest('id')->value('id'),
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

    private function storeVideoReel(Request $request, Stream $stream): JsonResponse
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
        );

        return response()->json([
            'image' => $reel->toGalleryPayload(),
        ], 201);
    }

    private function authorizeListen(Request $request, Stream $stream): void
    {
        $organization = $stream->organization;

        abort_unless(
            ($stream->is_public && ($organization?->is_public ?? false))
            || $request->user()?->canManageOrganization($organization),
            404
        );
    }

    private function authorizeUpload(Request $request, Stream $stream): void
    {
        $user = $request->user();
        $canManage = $user?->canManageOrganization($stream->organization);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }
}
