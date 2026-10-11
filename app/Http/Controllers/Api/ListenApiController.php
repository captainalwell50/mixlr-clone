<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Stream;
use App\Models\User;
use App\Support\ListenerMediaCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

class ListenApiController extends Controller
{
    public function discover(): JsonResponse
    {
        $streams = Stream::query()
            ->with('organization')
            ->where('is_public', true)
            ->where('status', StreamStatus::Live)
            ->whereHas('organization', fn ($q) => $q->where('is_public', true))
            ->latest('started_at')
            ->limit(40)
            ->get()
            ->map(fn (Stream $stream) => $this->card($stream));

        return response()->json(['streams' => $streams]);
    }

    public function show(Request $request, Stream $stream): JsonResponse
    {
        $this->resolveBearer($request);
        $this->authorizeListen($request, $stream);
        $stream->loadMissing('organization');
        $org = $stream->organization;

        $capability = app(ListenerMediaCapability::class);
        $gate = $capability->gateEvent($stream);
        $mediaQuery = $request->query('media');
        $hasCapability = $gate !== null && is_string($mediaQuery) && $mediaQuery !== '' && $capability->accepts($mediaQuery, $stream, $gate);
        $canManage = $org !== null && $request->user()?->canManageOrganization($org);

        $access = 'public';
        $mediaToken = null;
        $hlsUrl = $stream->hlsPlaylistUrl();
        $whepUrl = $stream->whepUrl();
        $playbackMode = $stream->playbackMode();
        $preferHls = $stream->preferHlsListen();
        $privateListen = false;

        if ($gate !== null) {
            $privateListen = true;
            $playbackMode = 'whep';
            $preferHls = false;
            $hlsUrl = null;

            if ($hasCapability) {
                $access = 'granted';
                $mediaToken = $mediaQuery;
                $whepUrl = $capability->withToken($stream->whepUrl(), $mediaQuery);
            } elseif ($canManage) {
                $access = 'granted';
                $mediaToken = $capability->issue($stream, $gate);
                $whepUrl = $capability->withToken($stream->whepUrl(), $mediaToken);
            } else {
                $access = 'password_required';
                $whepUrl = null;
            }
        }

        return response()->json([
            'access' => $access,
            'capability' => $mediaToken,
            'stream' => [
                'uuid' => $stream->uuid,
                'title' => $stream->title,
                'description' => $stream->description,
                'status' => $stream->status instanceof StreamStatus
                    ? $stream->status->value
                    : (string) $stream->status,
                'chat_enabled' => (bool) $stream->chat_enabled,
                'hls_url' => $hlsUrl,
                'whep_url' => $whepUrl,
                'playback_mode' => $playbackMode,
                'prefer_hls' => $preferHls,
                'private_listen' => $privateListen,
                'listen_background_url' => $stream->listenBackgroundUrl(),
            ],
            'organization' => $org ? [
                'name' => $org->name,
                'slug' => $org->slug,
                'theme_color' => $org->themeColor(),
                'logo_url' => $org->logoUrl(),
                'artwork_url' => $org->artworkUrl(),
                'creator_type' => $org->creator_type?->value,
                'giving' => $org->publicGiving(),
                'following' => $request->user()?->followsChannel($org) ?? false,
            ] : null,
        ]);
    }

    public function status(Request $request, Stream $stream): JsonResponse
    {
        $this->resolveBearer($request);
        $this->authorizeListen($request, $stream);

        return response()->json([
            'status' => $stream->status instanceof StreamStatus
                ? $stream->status->value
                : (string) $stream->status,
        ]);
    }

    public function unlock(Request $request, Stream $stream): JsonResponse
    {
        $this->resolveBearer($request);
        $this->authorizeListen($request, $stream);

        $throttleKey = 'listen-unlock:'.$stream->id.'|'.($request->user()?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Too many attempts. Try again shortly.',
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $password = trim((string) ($validated['password'] ?? ''));
        if ($password === '') {
            return response()->json([
                'message' => 'The password field is required.',
                'errors' => ['password' => ['The password field is required.']],
            ], 422);
        }

        $capability = app(ListenerMediaCapability::class);
        $gate = $capability->gateEvent($stream);
        if ($gate === null) {
            $latestEvent = Event::query()
                ->where('stream_id', $stream->id)
                ->latest('id')
                ->first();

            if ($latestEvent !== null && $latestEvent->status === EventStatus::Ended) {
                return response()->json([
                    'message' => 'This event has ended.',
                ], 422);
            }

            if ($latestEvent === null || $latestEvent->access !== EventAccess::Private) {
                return response()->json([
                    'message' => 'This broadcast does not require a password.',
                ], 422);
            }

            return response()->json([
                'message' => 'This event has ended.',
            ], 422);
        }

        if (! $gate->checkAccessPassword($password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => "That access password wasn't accepted.",
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        $token = $capability->issue($stream, $gate);
        $stream->loadMissing('organization');
        $org = $stream->organization;

        return response()->json([
            'access' => 'granted',
            'capability' => $token,
            'stream' => [
                'uuid' => $stream->uuid,
                'title' => $stream->title,
                'description' => $stream->description,
                'status' => $stream->status instanceof StreamStatus
                    ? $stream->status->value
                    : (string) $stream->status,
                'chat_enabled' => (bool) $stream->chat_enabled,
                'hls_url' => null,
                'whep_url' => $capability->withToken($stream->whepUrl(), $token),
                'playback_mode' => 'whep',
                'prefer_hls' => false,
                'private_listen' => true,
                'listen_background_url' => $stream->listenBackgroundUrl(),
            ],
            'organization' => $org ? [
                'name' => $org->name,
                'slug' => $org->slug,
                'theme_color' => $org->themeColor(),
                'logo_url' => $org->logoUrl(),
                'artwork_url' => $org->artworkUrl(),
                'creator_type' => $org->creator_type?->value,
                'giving' => $org->publicGiving(),
                'following' => $request->user()?->followsChannel($org) ?? false,
            ] : null,
        ]);
    }

    public function follow(Request $request, Stream $stream): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $stream->loadMissing('organization');
        $org = $stream->organization;
        if ($org === null) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        \App\Models\ChannelFollow::query()->firstOrCreate([
            'user_id' => $user->id,
            'organization_id' => $org->id,
        ]);

        return response()->json([
            'following' => true,
            'message' => __("You are following :name. We’ll notify you when they go on air.", [
                'name' => $org->name,
            ]),
        ]);
    }

    public function unfollow(Request $request, Stream $stream): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $stream->loadMissing('organization');
        $org = $stream->organization;
        if ($org === null) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        \App\Models\ChannelFollow::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $org->id)
            ->delete();

        return response()->json([
            'following' => false,
            'message' => __("Unfollowed :name.", ['name' => $org->name]),
        ]);
    }

    /** @return array<string, mixed> */
    private function card(Stream $stream): array
    {
        $org = $stream->organization;

        return [
            'uuid' => $stream->uuid,
            'title' => $stream->title,
            'status' => $stream->status instanceof StreamStatus
                ? $stream->status->value
                : (string) $stream->status,
            'organization' => $org?->name,
            'theme_color' => $org?->themeColor(),
            'logo_url' => $org?->logoUrl(),
            'artwork_url' => $org?->artworkUrl(),
            'creator_type' => $org?->creator_type?->value,
            'hls_url' => $stream->hlsPlaylistUrl(),
            'whep_url' => $stream->whepUrl(),
            'playback_mode' => $stream->playbackMode(),
            'prefer_hls' => $stream->preferHlsListen(),
        ];
    }


    /**
     * The listen route stays public. A bearer token is honored when present
     * so a manager can receive a private-event WHEP capability.
     */
    private function resolveBearer(Request $request): void
    {
        if ($request->user() !== null) {
            return;
        }

        $plain = $request->bearerToken();
        if (! is_string($plain) || $plain === '') {
            return;
        }

        $user = PersonalAccessToken::findToken($plain)?->tokenable;
        if ($user instanceof User) {
            auth()->setUser($user);
        }
    }

    private function authorizeListen(Request $request, Stream $stream): void
    {
        $organization = $stream->organization;
        $mediaQuery = $request->query('media');
        $capability = app(ListenerMediaCapability::class);
        $gate = $capability->gateEvent($stream);
        $hasCapability = $gate !== null && is_string($mediaQuery) && $mediaQuery !== '' && $capability->accepts($mediaQuery, $stream, $gate);

        abort_unless(
            ($stream->is_public && ($organization?->is_public ?? false))
            || $request->user()?->canManageOrganization($organization)
            || $hasCapability,
            404
        );
    }
}
