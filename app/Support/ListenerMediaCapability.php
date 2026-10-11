<?php

namespace App\Support;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Stream;
use DateTimeInterface;

/**
 * Short-lived listen capability for a private live event.
 *
 * Bound to the stream, the event, an expiry, and the listen purpose.
 * The event password is never placed in the token or the media URL.
 */
class ListenerMediaCapability
{
    public const QUERY_KEY = 'media';

    public const PURPOSE = 'listener_playback';

    public const LEGACY_PURPOSE = 'listen';

    public const TTL_MINUTES = 120;

    public function gateEvent(Stream $stream): ?Event
    {
        $event = Event::query()
            ->where('stream_id', $stream->id)
            ->whereIn('status', [EventStatus::Live, EventStatus::Paused])
            ->latest('id')
            ->first();

        if ($event === null || $event->access !== EventAccess::Private) {
            return null;
        }

        return $event;
    }

    /**
     * @return array{hls_url: ?string, whep_url: ?string}
     */
    public function urlsForEvent(Event $event): array
    {
        $event->loadMissing('stream');
        $stream = $event->stream;
        if ($stream === null) {
            return ['hls_url' => null, 'whep_url' => null];
        }

        if ($event->access !== EventAccess::Private) {
            return [
                'hls_url' => $stream->hlsPlaylistUrl(),
                'whep_url' => $stream->whepUrl(),
            ];
        }

        $token = $this->issue($stream, $event);

        return [
            'hls_url' => $this->withToken($this->originHlsUrl($stream), $token),
            'whep_url' => $this->withToken($stream->whepUrl(), $token),
        ];
    }

    public function issue(Stream $stream, Event $event, ?DateTimeInterface $expiresAt = null): string
    {
        $expires = $expiresAt ?? now()->addMinutes(self::TTL_MINUTES);
        $payload = strtolower((string) $stream->uuid)
            .'|'.$event->uuid
            .'|'.self::PURPOSE
            .'|'.$expires->getTimestamp()
            .'|'.$this->passwordFingerprint($event);
        $body = $this->base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $body, $this->key());

        return $body.'.'.$signature;
    }

    public function passwordFingerprint(Event $event): string
    {
        return substr(hash('sha256', (string) ($event->access_password ?? '')), 0, 10);
    }

    public function accepts(string $token, Stream $stream, Event $event): bool
    {
        if ($this->key() === '') {
            return false;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return false;
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, $this->key());
        if (strlen($signature) !== strlen($expected) || ! hash_equals($expected, $signature)) {
            return false;
        }

        $payload = $this->base64UrlDecode($body);
        if ($payload === null) {
            return false;
        }

        $fields = explode('|', $payload);
        if (count($fields) !== 4 && count($fields) !== 5) {
            return false;
        }

        $streamUuid = $fields[0];
        $eventUuid = $fields[1];
        $purpose = $fields[2];
        $expiresAt = $fields[3];
        $fingerprint = $fields[4] ?? null;

        if ($purpose !== self::PURPOSE && $purpose !== self::LEGACY_PURPOSE) {
            return false;
        }

        if (! ctype_digit($expiresAt)) {
            return false;
        }

        if (strtolower($streamUuid) !== strtolower((string) $stream->uuid)) {
            return false;
        }

        if (! hash_equals((string) $event->uuid, (string) $eventUuid)) {
            return false;
        }

        if ((int) $expiresAt < now()->getTimestamp()) {
            return false;
        }

        if ($fingerprint !== null && ! hash_equals($this->passwordFingerprint($event), $fingerprint)) {
            return false;
        }

        return true;
    }

    private function originHlsUrl(Stream $stream): string
    {
        return rtrim((string) config('streaming.mediamtx.hls_public_base'), '/')
            .'/'.$stream->hlsMediaPath()
            .'/index.m3u8';
    }

    public function withToken(string $url, string $token): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([self::QUERY_KEY => $token]);
    }

    private function key(): string
    {
        return (string) config('app.key');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
