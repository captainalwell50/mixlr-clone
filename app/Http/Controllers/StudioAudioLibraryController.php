<?php

namespace App\Http\Controllers;

use App\Models\Stream;
use App\Models\StudioAudioAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudioAudioLibraryController extends Controller
{
    public function index(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $q = trim((string) $request->query('q', ''));

        $assets = $stream->studioAudioAssets()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('title', 'like', $like)
                        ->orWhere('original_filename', 'like', $like);
                });
            })
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (StudioAudioAsset $asset) => $asset->toLibraryPayload($stream));

        return response()->json(['assets' => $assets]);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeStudio($request, $stream);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:51200', 'mimes:mp3,wav,m4a,aac,ogg,flac,webm,mp4'],
            'title' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['audio'];
        $original = $file->getClientOriginalName() ?: 'audio';
        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($original, PATHINFO_FILENAME) ?: 'Untitled';
        }
        $title = Str::limit($title, 255, '');

        $path = $file->store('studio-audio/'.$stream->uuid, 'public');

        $asset = StudioAudioAsset::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'uploaded_by' => $request->user()?->id,
            'title' => $title,
            'original_filename' => Str::limit($original, 255, ''),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'duration_seconds' => isset($validated['duration_seconds'])
                ? (int) round((float) $validated['duration_seconds'])
                : null,
        ]);

        return response()->json([
            'asset' => $asset->toLibraryPayload($stream),
        ], 201);
    }

    public function destroy(Request $request, Stream $stream, StudioAudioAsset $asset): JsonResponse
    {
        $this->authorizeStudio($request, $stream);
        abort_unless($asset->stream_id === $stream->id, 404);

        Storage::disk('public')->delete($asset->path);
        $asset->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeStudio(Request $request, Stream $stream): void
    {
        $user = $request->user();
        $canManage = $user?->canManageOrganization($stream->organization)
            || $user?->canManageStream($stream);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }
}
