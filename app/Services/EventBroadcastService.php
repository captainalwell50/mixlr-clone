<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Stream;
use App\Notifications\ChannelWentLiveNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class EventBroadcastService
{
    /**
     * Ensure the event has a linked Stream ready for WHIP/RTMP.
     */
    public function ensureStream(Event $event): Stream
    {
        if ($event->stream_id && $event->stream) {
            return $event->stream;
        }

        $stream = Stream::query()->create([
            'organization_id' => $event->organization_id,
            'uuid' => (string) Str::uuid(),
            'title' => $event->title,
            'description' => $event->description,
            'is_public' => $event->isDiscoverable(),
            'chat_enabled' => (bool) ($event->chat_enabled ?? true),
            'status' => StreamStatus::Offline,
        ]);

        $event->stream_id = $stream->id;
        $event->save();

        return $stream;
    }

    public function markLive(Event $event): void
    {
        $wasLive = $event->status === EventStatus::Live;

        $event->status = EventStatus::Live;
        $event->started_at ??= now();
        $event->ended_at = null;
        $event->save();

        if ($event->stream) {
            $event->stream->status = StreamStatus::Live;
            $event->stream->started_at ??= now();
            $event->stream->ended_at = null;
            $event->stream->save();
        }

        if (! $wasLive) {
            $this->notifyFollowers($event);
        }
    }

    public function markEnded(Event $event): void
    {
        $event->status = EventStatus::Ended;
        $event->ended_at = now();
        $event->save();

        if ($event->stream) {
            $event->stream->status = StreamStatus::Offline;
            $event->stream->ended_at = now();
            $event->stream->save();
        }
    }

    public function notifyFollowers(Event $event): void
    {
        $event->loadMissing('organization.followers');
        $followers = $event->organization->followers;

        if ($followers->isEmpty()) {
            return;
        }

        Notification::send($followers, new ChannelWentLiveNotification($event));
    }
}
