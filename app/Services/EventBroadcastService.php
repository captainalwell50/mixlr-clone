<?php

namespace App\Services;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Jobs\SyncRecordingToObjectStorage;
use App\Models\Event;
use App\Models\Recording;
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

    /**
     * Live or paused session still open on this stream (not ended).
     */
    public function openEventForStream(Stream $stream): ?Event
    {
        return Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Live, EventStatus::Paused, EventStatus::Scheduled])
            ->latest('id')
            ->first();
    }

    /**
     * Default auto-created event title (distinct from Mixlr’s “{Name}'s live event”).
     * Example: “Live from Dunamis Radio”.
     */
    public function defaultEventTitle(Stream $stream): string
    {
        $stream->loadMissing('organization');

        $channel = trim((string) ($stream->organization?->name ?? ''));
        if ($channel === '') {
            $channel = trim((string) $stream->title);
        }
        if ($channel === '') {
            return 'Live · '.now()->timezone(config('app.timezone'))->format('M j');
        }

        return 'Live from '.$channel;
    }

    /**
     * Create a named event on an existing studio stream (does not go live yet).
     */
    public function createEventOnStream(Stream $stream, string $title, ?string $description = null): Event
    {
        $title = trim($title) !== '' ? trim($title) : $this->defaultEventTitle($stream);

        return Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => $title,
            'description' => $description,
            'status' => EventStatus::Scheduled,
            'access' => EventAccess::Public,
            'chat_enabled' => (bool) ($stream->chat_enabled ?? true),
            'show_listener_count' => true,
            'scheduled_at' => now(),
        ]);
    }

    /**
     * Go live: resume open event, use explicit event, or auto-create a new one.
     */
    public function goLiveOnStream(Stream $stream, ?string $title = null, ?Event $event = null): Event
    {
        if ($event !== null) {
            abort_unless($event->stream_id === $stream->id, 422, 'Event is not linked to this stream.');
            abort_unless(
                in_array($event->status, [EventStatus::Scheduled, EventStatus::Live, EventStatus::Paused], true),
                422,
                'This event has ended. Create a new event or go live again.',
            );
            $this->markLive($event);

            return $event->fresh(['organization', 'stream']);
        }

        $open = $this->openEventForStream($stream);
        if ($open !== null) {
            $this->markLive($open);

            return $open->fresh(['organization', 'stream']);
        }

        $created = $this->createEventOnStream(
            $stream,
            trim((string) $title) !== '' ? trim((string) $title) : $this->defaultEventTitle($stream),
        );
        $this->markLive($created);

        return $created->fresh(['organization', 'stream']);
    }

    public function markLive(Event $event): void
    {
        $wasLive = $event->status === EventStatus::Live;
        $wasPaused = $event->status === EventStatus::Paused;

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

        // Notify once when first going live — not when resuming from pause.
        if (! $wasLive && ! $wasPaused) {
            $this->notifyFollowers($event);
        }
    }

    public function markPaused(Event $event): void
    {
        if ($event->status === EventStatus::Ended) {
            return;
        }

        $event->status = EventStatus::Paused;
        $event->save();

        if ($event->stream) {
            $event->stream->status = StreamStatus::Offline;
            $event->stream->ended_at = now();
            $event->stream->save();
        }
    }

    public function markEnded(Event $event): void
    {
        $event->status = EventStatus::Ended;
        $event->ended_at = now();
        $event->scripture_ref = null;
        $event->scripture_text = null;
        $event->scripture_updated_at = null;
        $event->save();

        if ($event->stream) {
            $event->stream->status = StreamStatus::Offline;
            $event->stream->ended_at = now();
            $event->stream->save();
        }

        $this->publishHeldMediaMtxRecordings($event);
    }

    /**
     * Segments captured during pause/reconnect stay private until the event ends,
     * unless a Studio upload already owns the public podcast slot.
     */
    public function publishHeldMediaMtxRecordings(Event $event): void
    {
        $studioPublic = Recording::query()
            ->where('event_id', $event->id)
            ->where('source', Recording::SOURCE_STUDIO)
            ->where('is_public', true)
            ->exists();

        if ($studioPublic) {
            return;
        }

        $held = Recording::query()
            ->where('event_id', $event->id)
            ->where('source', Recording::SOURCE_MEDIAMTX)
            ->where('is_public', false)
            ->get();

        foreach ($held as $recording) {
            $recording->is_public = true;
            if (! filled($recording->title)) {
                $recording->title = $event->title;
            }
            $recording->save();

            if (config('object_storage.enabled')) {
                SyncRecordingToObjectStorage::dispatch($recording->id);
            }
        }
    }

    public function notifyFollowers(Event $event): void
    {
        $event->loadMissing('organization.followers', 'organization.subscription.plan');
        $organization = $event->organization;
        if ($organization === null) {
            return;
        }
        // Only enforce when a plan is attached; grandfather orgs without a plan.
        if ($organization->plan() !== null && ! $organization->hasFeature('listener_notifications')) {
            return;
        }

        $followers = $organization->followers;

        if ($followers->isEmpty()) {
            return;
        }

        Notification::send($followers, new ChannelWentLiveNotification($event));
    }

    /** @return array<string, mixed> */
    public function eventPayload(Event $event): array
    {
        $event->loadMissing('organization', 'stream');

        return [
            'id' => $event->id,
            'uuid' => $event->uuid,
            'title' => $event->title,
            'status' => $event->status->value,
            'url' => route('events.show', $event),
            'started_at' => $event->started_at?->toIso8601String(),
            'ended_at' => $event->ended_at?->toIso8601String(),
        ];
    }
}
