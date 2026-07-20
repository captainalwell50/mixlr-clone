<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventHeart;
use App\Models\ListenerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventEngageController extends Controller
{
    public function presence(Request $request, Event $event): JsonResponse
    {
        $sessionKey = substr((string) (
            $request->input('session_key')
            ?: $request->cookie('listener_sid')
            ?: $request->session()->getId()
            ?: Str::uuid()
        ), 0, 64);

        $session = ListenerSession::query()->firstOrNew([
            'event_id' => $event->id,
            'session_key' => $sessionKey,
        ]);

        if (! $session->exists) {
            $session->started_at = now();
        }

        $session->last_seen_at = now();
        $session->user_id = $request->user()?->id ?? $session->user_id;
        $session->save();

        $hearts = $event->hearts()->count();

        return response()->json([
            'session_key' => $session->session_key,
            'listeners' => $event->show_listener_count ? $event->activeListenerCount() : null,
            'hearts' => $hearts,
            'likes' => $hearts,
        ]);
    }

    public function heart(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Login required.'], 401);
        }

        EventHeart::query()->firstOrCreate([
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);

        $hearts = $event->hearts()->count();

        return response()->json([
            'hearted' => true,
            'liked' => true,
            'hearts' => $hearts,
            'likes' => $hearts,
        ]);
    }
}
