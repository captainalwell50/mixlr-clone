<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Event;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function indexForEvent(Request $request, Event $event): JsonResponse
    {
        if (! $event->chat_enabled) {
            return response()->json(['messages' => [], 'enabled' => false]);
        }

        $afterId = (int) $request->query('after', 0);

        $messages = ChatMessage::query()
            ->where('event_id', $event->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'display_name', 'body', 'created_at']);

        return response()->json([
            'enabled' => true,
            'requires_auth' => true,
            'messages' => $messages->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'body' => $m->body,
                'at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storeForEvent(Request $request, Event $event): JsonResponse
    {
        if (! $event->chat_enabled) {
            return response()->json(['message' => 'Chat is disabled.'], 403);
        }

        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Login required to chat.'], 401);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        if (! $event->stream_id) {
            app(\App\Services\EventBroadcastService::class)->ensureStream($event);
            $event->refresh();
        }

        $message = ChatMessage::query()->create([
            'event_id' => $event->id,
            'stream_id' => $event->stream_id,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'body' => trim($validated['body']),
        ]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'name' => $message->display_name,
                'body' => $message->body,
                'at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /** @deprecated Prefer event chat routes */
    public function index(Request $request, Stream $stream): JsonResponse
    {
        $event = Event::query()->where('stream_id', $stream->id)->latest('id')->first();
        if ($event) {
            return $this->indexForEvent($request, $event);
        }

        if (! $stream->chat_enabled) {
            return response()->json(['messages' => [], 'enabled' => false]);
        }

        $afterId = (int) $request->query('after', 0);
        $messages = ChatMessage::query()
            ->where('stream_id', $stream->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'display_name', 'body', 'created_at']);

        return response()->json([
            'enabled' => true,
            'requires_auth' => true,
            'messages' => $messages->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'body' => $m->body,
                'at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** @deprecated Prefer event chat routes */
    public function store(Request $request, Stream $stream): JsonResponse
    {
        $event = Event::query()->where('stream_id', $stream->id)->latest('id')->first();
        if ($event) {
            return $this->storeForEvent($request, $event);
        }

        if (! $stream->chat_enabled) {
            return response()->json(['message' => 'Chat is disabled.'], 403);
        }

        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Login required to chat.'], 401);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        $message = ChatMessage::query()->create([
            'stream_id' => $stream->id,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'body' => trim($validated['body']),
        ]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'name' => $message->display_name,
                'body' => $message->body,
                'at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
