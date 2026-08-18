<?php

namespace App\Http\Controllers;

use App\Models\Stream;
use App\Models\StreamLike;
use App\Services\ListenerPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StreamEngageController extends Controller
{
    public function __construct(
        private ListenerPresenceService $presence,
    ) {}

    public function presence(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeListen($request, $stream);

        $sessionKey = substr((string) (
            $request->input('session_key')
            ?: $request->cookie('listener_sid')
            ?: ($request->hasSession() ? $request->session()->getId() : null)
            ?: Str::uuid()
        ), 0, 64);

        $result = $this->presence->touchStream(
            $stream,
            $sessionKey,
            $request->user()?->id ?? $request->user('sanctum')?->id,
        );

        return response()->json([
            'session_key' => $result['session_key'],
            'listeners' => $result['listeners'],
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
