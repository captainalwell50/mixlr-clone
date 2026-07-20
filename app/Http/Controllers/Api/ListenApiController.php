<?php

namespace App\Http\Controllers\Api;

use App\Enums\StreamStatus;
use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListenApiController extends Controller
{
    public function discover(): JsonResponse
    {
        $streams = Stream::query()
            ->with('organization')
            ->where('is_public', true)
            ->where('status', StreamStatus::Live)
            ->whereHas('organization', fn ($q) => $q->where('is_public', true))
            ->latest('started_at')
            ->limit(40)
            ->get()
            ->map(fn (Stream $stream) => $this->card($stream));

        return response()->json(['streams' => $streams]);
    }

    public function show(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);
        $stream->loadMissing('organization');

        $org = $stream->organization;

        return response()->json([
            'stream' => [
                'uuid' => $stream->uuid,
                'title' => $stream->title,
                'description' => $stream->description,
                'status' => $stream->status instanceof StreamStatus
                    ? $stream->status->value
                    : (string) $stream->status,
                'chat_enabled' => (bool) $stream->chat_enabled,
                'hls_url' => $stream->hlsPlaylistUrl(),
                'whep_url' => $stream->whepUrl(),
                'listen_background_url' => $stream->listenBackgroundUrl(),
            ],
            'organization' => $org ? [
                'name' => $org->name,
                'slug' => $org->slug,
                'theme_color' => $org->themeColor(),
                'artwork_url' => $org->artworkUrl(),
                'creator_type' => $org->creator_type?->value,
            ] : null,
        ]);
    }

    public function status(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);

        return response()->json([
            'status' => $stream->status instanceof StreamStatus
                ? $stream->status->value
                : (string) $stream->status,
        ]);
    }

    /** @return array<string, mixed> */
    private function card(Stream $stream): array
    {
        $org = $stream->organization;

        return [
            'uuid' => $stream->uuid,
            'title' => $stream->title,
            'status' => $stream->status instanceof StreamStatus
                ? $stream->status->value
                : (string) $stream->status,
            'organization' => $org?->name,
            'theme_color' => $org?->themeColor(),
            'artwork_url' => $org?->artworkUrl(),
            'hls_url' => $stream->hlsPlaylistUrl(),
        ];
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
}
