<?php

namespace App\Http\Controllers\Api;

use App\Enums\StreamStatus;
use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorApiController extends Controller
{
    public function home(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $organization = $user->organizations()
            ->with(['subscription.plan', 'streams'])
            ->orderByPivot('role')
            ->first();

        if ($organization === null) {
            return response()->json([
                'onboarded' => false,
                'organization' => null,
                'stream' => null,
                'can_broadcast' => false,
            ]);
        }

        $stream = $organization->defaultStream();

        return response()->json([
            'onboarded' => true,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'creator_type' => $organization->creator_type?->value,
                'theme_color' => $organization->themeColor(),
                'artwork_url' => $organization->artworkUrl(),
                'channel_url' => route('channels.show', $organization),
            ],
            'stream' => $stream ? $this->streamSummary($stream) : null,
            'streams' => $organization->streams->map(fn (Stream $s) => $this->streamSummary($s))->values(),
            'can_broadcast' => $organization->allowsBroadcast(),
            'subscription' => [
                'status' => $organization->subscription?->status?->value,
                'plan' => $organization->subscription?->plan?->name,
            ],
        ]);
    }

    public function publish(Request $request, Stream $stream): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->canManageStream($stream), 403);

        $organization = $stream->organization;
        abort_unless($organization?->allowsBroadcast() ?? false, 402, 'An active subscription is required to go on air.');

        return response()->json([
            'stream' => $this->streamSummary($stream),
            'whip_url' => $stream->whipUrl(),
            'whep_url' => $stream->whepUrl(),
            'hls_url' => $stream->hlsPlaylistUrl(),
            'rtmp_url' => $stream->rtmpUrl(),
            'rtmp_stream_key' => $stream->rtmpStreamKeyForObs(),
            'media_path' => $stream->mediaPath(),
            'can_broadcast' => true,
        ]);
    }

    public function goLive(Request $request, Stream $stream): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->canManageStream($stream), 403);
        abort_unless($stream->organization?->allowsBroadcast() ?? false, 402);

        $stream->forceFill([
            'status' => StreamStatus::Live,
            'started_at' => $stream->started_at ?? now(),
            'ended_at' => null,
        ])->save();

        return response()->json([
            'stream' => $this->streamSummary($stream->fresh()),
            'publish' => [
                'whip_url' => $stream->whipUrl(),
                'whep_url' => $stream->whepUrl(),
                'hls_url' => $stream->hlsPlaylistUrl(),
            ],
        ]);
    }

    public function end(Request $request, Stream $stream): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->canManageStream($stream), 403);

        $stream->forceFill([
            'status' => StreamStatus::Offline,
            'ended_at' => now(),
        ])->save();

        return response()->json([
            'stream' => $this->streamSummary($stream->fresh()),
        ]);
    }

    /** @return array<string, mixed> */
    private function streamSummary(Stream $stream): array
    {
        return [
            'id' => $stream->id,
            'uuid' => $stream->uuid,
            'title' => $stream->title,
            'description' => $stream->description,
            'status' => $stream->status instanceof StreamStatus
                ? $stream->status->value
                : (string) $stream->status,
            'is_public' => (bool) $stream->is_public,
            'chat_enabled' => (bool) $stream->chat_enabled,
            'listen_url' => route('listen.stream', $stream),
        ];
    }
}
