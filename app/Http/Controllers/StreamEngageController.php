<?php

namespace App\Http\Controllers;

use App\Models\Stream;
use App\Models\StreamLike;
use App\Models\StreamListenerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StreamEngageController extends Controller
{
    public function presence(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);

        $sessionKey = substr((string) (
            $request->input('session_key')
            ?: $request->cookie('listener_sid')
            ?: ($request->hasSession() ? $request->session()->getId() : null)
            ?: Str::uuid()
        ), 0, 64);

        $session = StreamListenerSession::query()->firstOrNew([
            'stream_id' => $stream->id,
            'session_key' => $sessionKey,
        ]);

        if (! $session->exists) {
            $session->started_at = now();
        }

        $session->last_seen_at = now();
        $session->user_id = $request->user()?->id ?? $session->user_id;
        $session->save();

        return response()->json([
            'session_key' => $session->session_key,
            'listeners' => $stream->activeListenerCount(),
            'likes' => $stream->likes()->count(),
        ]);
    }

    public function like(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);

        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Login required.'], 401);
        }

        StreamLike::query()->firstOrCreate([
            'stream_id' => $stream->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'liked' => true,
            'likes' => $stream->likes()->count(),
        ]);
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
