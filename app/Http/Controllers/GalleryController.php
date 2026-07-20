<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Stream;
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
            ->map(fn (GalleryImage $image) => [
                'id' => $image->id,
                'url' => $image->url(),
                'caption' => $image->caption,
                'created_at' => $image->created_at?->toIso8601String(),
            ]);

        return response()->json(['images' => $images]);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeUpload($request, $stream);

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
            'caption' => $validated['caption'] ?? null,
            'sort_order' => 0,
        ]);

        return response()->json([
            'image' => [
                'id' => $image->id,
                'url' => $image->url(),
                'caption' => $image->caption,
            ],
        ], 201);
    }

    public function destroy(Request $request, Stream $stream, GalleryImage $image): JsonResponse
    {
        $this->authorizeUpload($request, $stream);
        abort_unless($image->stream_id === $stream->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['ok' => true]);
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
