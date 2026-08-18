<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ListenerSession;
use App\Models\Stream;
use App\Models\StreamListenerSession;
use Illuminate\Support\Collection;

class ListenerPresenceService
{
    public const HEARTBEAT_SECONDS = 75;

    /**
     * Record presence for a stream listen session and mirror onto the open service event when linked.
     *
     * @return array{session_key: string, listeners: int}
     */
    public function touchStream(Stream $stream, string $sessionKey, ?int $userId = null): array
    {
        $this->upsertStreamSession($stream, $sessionKey, $userId);

        $event = $stream->openServiceEvent();
        if ($event !== null) {
            $this->upsertEventSession($event, $sessionKey, $userId);
        }

        return [
            'session_key' => $sessionKey,
            'listeners' => $this->countForStream($stream),
        ];
    }

    /**
     * Record presence for an event listen session and mirror onto the linked stream when present.
     *
     * @return array{session_key: string, listeners: int|null}
     */
    public function touchEvent(Event $event, string $sessionKey, ?int $userId = null): array
    {
        $this->upsertEventSession($event, $sessionKey, $userId);

        $stream = $event->stream;
        if ($stream !== null) {
            $this->upsertStreamSession($stream, $sessionKey, $userId);
        }

        return [
            'session_key' => $sessionKey,
            'listeners' => $event->show_listener_count ? $this->countForEvent($event) : null,
        ];
    }

    public function countForStream(Stream $stream, int $withinSeconds = self::HEARTBEAT_SECONDS): int
    {
        $since = now()->subSeconds($withinSeconds);

        $rows = StreamListenerSession::query()
            ->where('stream_id', $stream->id)
            ->where('last_seen_at', '>=', $since)
            ->get(['session_key', 'user_id']);

        $event = $stream->openServiceEvent();
        if ($event !== null) {
            $rows = $rows->concat(
                ListenerSession::query()
                    ->where('event_id', $event->id)
                    ->where('last_seen_at', '>=', $since)
                    ->get(['session_key', 'user_id'])
            );
        }

        return $this->uniqueListenerCount($rows);
    }

    public function countForEvent(Event $event, int $withinSeconds = self::HEARTBEAT_SECONDS): int
    {
        $since = now()->subSeconds($withinSeconds);

        $rows = ListenerSession::query()
            ->where('event_id', $event->id)
            ->where('last_seen_at', '>=', $since)
            ->get(['session_key', 'user_id']);

        if ($event->stream_id) {
            $rows = $rows->concat(
                StreamListenerSession::query()
                    ->where('stream_id', $event->stream_id)
                    ->where('last_seen_at', '>=', $since)
                    ->get(['session_key', 'user_id'])
            );
        }

        return $this->uniqueListenerCount($rows);
    }

    private function upsertStreamSession(Stream $stream, string $sessionKey, ?int $userId): void
    {
        $session = StreamListenerSession::query()->firstOrNew([
            'stream_id' => $stream->id,
            'session_key' => $sessionKey,
        ]);

        if (! $session->exists) {
            $session->started_at = now();
        }

        $session->last_seen_at = now();
        $session->user_id = $userId ?? $session->user_id;
        $session->save();
    }

    private function upsertEventSession(Event $event, string $sessionKey, ?int $userId): void
    {
        $session = ListenerSession::query()->firstOrNew([
            'event_id' => $event->id,
            'session_key' => $sessionKey,
        ]);

        if (! $session->exists) {
            $session->started_at = now();
        }

        $session->last_seen_at = now();
        $session->user_id = $userId ?? $session->user_id;
        $session->save();
    }

    /**
     * Unique concurrent listeners: prefer user_id when present so web+mobile
     * for the same account count once; otherwise session_key.
     *
     * @param  Collection<int, object{session_key: string, user_id: ?int}>  $rows
     */
    private function uniqueListenerCount(Collection $rows): int
    {
        $seen = [];

        foreach ($rows as $row) {
            $userId = $row->user_id ?? null;
            $key = $userId !== null
                ? 'u:'.$userId
                : 's:'.(string) $row->session_key;
            $seen[$key] = true;
        }

        return count($seen);
    }
}
