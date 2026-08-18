<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventHeart;
use App\Services\ListenerPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventEngageController extends Controller
{
    public function __construct(
        private ListenerPresenceService $presence,
    ) {}

    public function presence(Request $request, Event $event): JsonResponse
    {
        $sessionKey = substr((string) (
            $request->input('session_key')
            ?: $request->cookie('listener_sid')
            ?: $request->session()->getId()
            ?: Str::uuid()
        ), 0, 64);

        $result = $this->presence->touchEvent(
            $event,
            $sessionKey,
            $request->user()?->id,
        );

        $hearts = $event->hearts()->count();

        return response()->json([
            'session_key' => $result['session_key'],
            'listeners' => $result['listeners'],
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
