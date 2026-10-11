<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Support\ListenerMediaCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateLiveMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_event_can_listen_without_a_media_capability(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Public);

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
        ])->assertOk();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'playback',
            'protocol' => 'hls',
            'path' => $stream->hlsMediaPath(),
        ])->assertOk();

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee($stream->whepUrl(), false)
            ->assertDontSee('media=', false);

        $this->getJson(route('events.status', $event))
            ->assertOk()
            ->assertJsonPath('whep_url', $stream->whepUrl())
            ->assertJsonPath('hls_url', $stream->hlsPlaylistUrl());
    }

    public function test_locked_private_event_denies_whep_and_hls_and_hides_credentials(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123');

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Enter the password', false)
            ->assertDontSee($stream->whepUrl(), false)
            ->assertDontSee('index.m3u8', false)
            ->assertDontSee('secret123', false)
            ->assertDontSee($stream->uuid, false);

        $this->get(route('events.status', $event))
            ->assertForbidden()
            ->assertDontSee('whep', false)
            ->assertDontSee('index.m3u8', false)
            ->assertDontSee('secret123', false);

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
        ])->assertForbidden();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'playback',
            'protocol' => 'hls',
            'path' => $stream->hlsMediaPath(),
        ])->assertForbidden();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'rtsp',
            'ip' => '127.0.0.1',
            'path' => $stream->mediaPath(),
        ])->assertOk();
    }

    public function test_unlocked_private_event_allows_whep_and_hls_with_capability(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123');

        $this->post(route('events.unlock', $event), ['password' => 'secret123'])
            ->assertRedirect(route('events.show', $event));

        $status = $this->getJson(route('events.status', $event))->assertOk();
        $whep = (string) $status->json('whep_url');
        $hls = (string) $status->json('hls_url');

        $this->assertStringStartsWith($stream->whepUrl(), $whep);
        $this->assertStringContainsString('media=', $whep);
        $this->assertStringNotContainsString('secret123', $whep);
        $this->assertStringContainsString('media=', $hls);
        $this->assertStringNotContainsString('secret123', $hls);

        $token = $this->tokenFrom($whep);

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$token,
        ])->assertOk();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'playback',
            'protocol' => 'hls',
            'path' => $stream->hlsMediaPath(),
            'query' => 'media='.$token,
        ])->assertOk();
    }

    public function test_expired_wrong_stream_and_tampered_capabilities_are_denied(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123');
        [$other] = $this->liveEvent(EventAccess::Private, 'other-secret');
        $capability = app(ListenerMediaCapability::class);

        $expired = $capability->issue($stream, $event, now()->subMinute());
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$expired,
        ])->assertForbidden();

        $foreign = $capability->issue($other, $event, now()->addHour());
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$foreign,
        ])->assertForbidden();

        $valid = $capability->issue($stream, $event, now()->addHour());
        $tampered = substr($valid, 0, -1).(str_ends_with($valid, 'a') ? 'b' : 'a');
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$tampered,
        ])->assertForbidden();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'path' => $other->mediaPath(),
            'query' => 'media='.$valid,
        ])->assertForbidden();
    }

    public function test_native_private_listen_is_whep_only_and_cannot_publish(): void
    {
        config(['streaming.mediamtx.publish_secret' => 'church-secret']);
        [$stream] = $this->liveEvent(EventAccess::Private, 'secret123');
        $manager = \App\Models\User::factory()->create();
        $stream->organization->users()->attach($manager->id, ['role' => \App\Enums\OrgRole::Owner->value]);

        $this->getJson('/api/v1/listen/'.$stream->uuid)->assertNotFound();

        $bearer = $manager->createToken('listen')->plainTextToken;
        $this->withToken($bearer)
            ->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.private_listen', true)
            ->assertJsonPath('stream.hls_url', null);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('stream.private_listen', true)
            ->assertJsonPath('stream.hls_url', null)
            ->assertJsonPath('stream.playback_mode', 'whep')
            ->assertJsonPath('stream.prefer_hls', false);

        $whep = (string) $response->json('stream.whep_url');
        $token = $this->tokenFrom($whep);
        $this->assertNotSame('', $token);

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$token,
        ])->assertOk();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
            'password' => $token,
            'query' => 'media='.$token,
        ])->assertForbidden();
    }

    public function test_native_private_event_unlock_provides_media_capability(): void
    {
        [$stream] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);

        // Before unlock, listener-visible private event returns structured password_required
        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('access', 'password_required')
            ->assertJsonPath('stream.whep_url', null);

        // Wrong password rejected with 422
        $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'wrongpass',
        ])->assertStatus(422)
          ->assertJsonPath('message', "That access password wasn't accepted.");

        // Correct password returns unlocked stream with WHEP capability
        $response = $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('access', 'granted')
            ->assertJsonPath('stream.private_listen', true)
            ->assertJsonPath('stream.hls_url', null)
            ->assertJsonPath('stream.playback_mode', 'whep');

        $whep = (string) $response->json('stream.whep_url');
        $token = $this->tokenFrom($whep);
        $this->assertNotSame('', $token);

        // Can also fetch stream with minted token query
        $this->getJson('/api/v1/listen/'.$stream->uuid.'?media='.$token)
            ->assertOk()
            ->assertJsonPath('access', 'granted')
            ->assertJsonPath('stream.private_listen', true);
    }

    public function test_blank_and_whitespace_password_rejected(): void
    {
        [$stream] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);

        $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => '',
        ])->assertStatus(422);

        $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => '   ',
        ])->assertStatus(422);

        $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [])
            ->assertStatus(422);
    }

    public function test_listener_visible_private_event_returns_structured_password_required_state(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);

        $response = $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('access', 'password_required')
            ->assertJsonPath('capability', null)
            ->assertJsonPath('stream.private_listen', true)
            ->assertJsonPath('stream.hls_url', null)
            ->assertJsonPath('stream.whep_url', null)
            ->assertJsonPath('stream.playback_mode', 'whep')
            ->assertJsonPath('stream.title', 'Main')
            ->assertJsonPath('organization.name', 'Private Org');

        // Confirm credentials and secrets are not leaked
        $this->assertStringNotContainsString('secret123', $response->getContent());
        $this->assertStringNotContainsString((string) $event->access_password, $response->getContent());
    }

    public function test_hidden_unlisted_stream_returns_404_for_stranger(): void
    {
        // Stream is not public
        [$stream] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: false);

        // Non-disclosure: GET returns 404
        $this->getJson('/api/v1/listen/'.$stream->uuid)->assertNotFound();

        // Non-disclosure: POST unlock returns 404 (does not leak existence of unlisted stream)
        $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'secret123',
        ])->assertNotFound();

        $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'wrongpass',
        ])->assertNotFound();
    }

    public function test_unlock_rate_limiting_enforces_5_attempts_per_minute(): void
    {
        [$stream] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);

        // First 5 attempts fail with 422
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
                'password' => 'wrongpass',
            ])->assertStatus(422)
              ->assertJsonPath('message', "That access password wasn't accepted.");
        }

        // 6th attempt is throttled with 429
        $response = $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'wrongpass',
        ])->assertStatus(429)
          ->assertJsonPath('message', 'Too many attempts. Try again shortly.');

        $this->assertTrue($response->headers->has('Retry-After'));
    }

    public function test_unlock_ended_or_public_event_returns_appropriate_error(): void
    {
        [$streamPublic, $eventPublic] = $this->liveEvent(EventAccess::Public);

        // Public broadcast does not require password
        $this->postJson('/api/v1/listen/'.$streamPublic->uuid.'/unlock', [
            'password' => 'anything',
        ])->assertStatus(422)
          ->assertJsonPath('message', 'This broadcast does not require a password.');

        // Ended private event
        [$streamEnded, $eventEnded] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);
        $eventEnded->update(['status' => EventStatus::Ended]);

        $this->postJson('/api/v1/listen/'.$streamEnded->uuid.'/unlock', [
            'password' => 'secret123',
        ])->assertStatus(422)
          ->assertJsonPath('message', 'This event has ended.');
    }

    public function test_password_change_invalidates_previously_minted_capability(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'initialPassword', streamPublic: true);

        // Unlock with original password
        $unlockResp = $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'initialPassword',
        ])->assertOk();

        $token = (string) $unlockResp->json('capability');
        $this->assertNotSame('', $token);

        // MediaMTX auth allows read with valid token
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$token,
        ])->assertOk();

        // API accepts token
        $this->getJson('/api/v1/listen/'.$stream->uuid.'?media='.$token)
            ->assertOk()
            ->assertJsonPath('access', 'granted');

        // Broadcaster updates password
        $event->update(['access_password' => 'newPassword456']);

        // Previously minted token is now rejected by MediaMTX auth
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$token,
        ])->assertForbidden();

        // API now returns password_required instead of granted
        $this->getJson('/api/v1/listen/'.$stream->uuid.'?media='.$token)
            ->assertOk()
            ->assertJsonPath('access', 'password_required')
            ->assertJsonPath('stream.whep_url', null);

        // Re-unlocking with new password succeeds
        $newUnlockResp = $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'newPassword456',
        ])->assertOk()
          ->assertJsonPath('access', 'granted');

        $newToken = (string) $newUnlockResp->json('capability');
        $this->assertNotSame($token, $newToken);

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$newToken,
        ])->assertOk();
    }

    public function test_legacy_listen_token_backward_compatibility(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123');
        $capability = app(ListenerMediaCapability::class);

        // Fabricate legacy 4-part token with 'listen' purpose
        $expires = now()->addHour()->getTimestamp();
        $legacyPayload = strtolower((string) $stream->uuid).'|'.$event->uuid.'|listen|'.$expires;
        $body = rtrim(strtr(base64_encode($legacyPayload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $body, (string) config('app.key'));
        $legacyToken = $body.'.'.$sig;

        $this->assertTrue($capability->accepts($legacyToken, $stream, $event));

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$legacyToken,
        ])->assertOk();
    }

    public function test_wrong_capability_purpose_is_rejected(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);
        $capability = app(ListenerMediaCapability::class);

        // Token with unauthorized purpose 'studio' or 'digital_library'
        $expires = now()->addHour()->getTimestamp();
        $payload = strtolower((string) $stream->uuid).'|'.$event->uuid.'|studio|'.$expires.'|'.$capability->passwordFingerprint($event);
        $body = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $body, (string) config('app.key'));
        $wrongToken = $body.'.'.$sig;

        $this->assertFalse($capability->accepts($wrongToken, $stream, $event));

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$wrongToken,
        ])->assertForbidden();

        $this->getJson('/api/v1/listen/'.$stream->uuid.'?media='.$wrongToken)
            ->assertOk()
            ->assertJsonPath('access', 'password_required')
            ->assertJsonPath('stream.whep_url', null);
    }

    public function test_reconnect_with_valid_capability_does_not_ask_for_password(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);

        // Initial unlock
        $unlockResp = $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'secret123',
        ])->assertOk();

        $token = (string) $unlockResp->json('capability');
        $this->assertNotSame('', $token);

        // Reconnect attempt provides saved capability
        $reconnectResp = $this->getJson('/api/v1/listen/'.$stream->uuid.'?media='.$token)
            ->assertOk()
            ->assertJsonPath('access', 'granted')
            ->assertJsonPath('capability', $token)
            ->assertJsonPath('stream.private_listen', true);

        $whep = (string) $reconnectResp->json('stream.whep_url');
        $this->assertStringContainsString('media='.$token, $whep);

        // MediaMTX playback authorized
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
            'query' => 'media='.$token,
        ])->assertOk();
    }

    public function test_private_to_public_transition_removes_password_requirement(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Private, 'secret123', streamPublic: true);

        // Initially requires password
        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('access', 'password_required')
            ->assertJsonPath('stream.whep_url', null);

        // Creator switches event to public
        $event->update([
            'access' => EventAccess::Public,
            'access_password' => null,
        ]);

        // Future requests no longer require password
        $resp = $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('access', 'public')
            ->assertJsonPath('stream.private_listen', false)
            ->assertJsonPath('capability', null);

        $this->assertNotNull($resp->json('stream.whep_url'));
        $this->assertNotNull($resp->json('stream.hls_url'));

        // MediaMTX reads succeed without token
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
        ])->assertOk();
    }

    public function test_public_to_private_transition_requires_unlock(): void
    {
        [$stream, $event] = $this->liveEvent(EventAccess::Public);

        // Initially public
        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('access', 'public')
            ->assertJsonPath('stream.private_listen', false);

        // Creator switches event to private
        $event->update([
            'access' => EventAccess::Private,
            'access_password' => 'newSecret999',
        ]);

        // Now requires password and omits URLs
        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('access', 'password_required')
            ->assertJsonPath('stream.whep_url', null);

        // MediaMTX denies access without capability
        $this->postJson('/api/mediamtx/auth', [
            'action' => 'read',
            'protocol' => 'webrtc',
            'path' => $stream->mediaPath(),
        ])->assertForbidden();

        // Unlock with new password grants capability
        $this->postJson('/api/v1/listen/'.$stream->uuid.'/unlock', [
            'password' => 'newSecret999',
        ])
            ->assertOk()
            ->assertJsonPath('access', 'granted');
    }

    public function test_private_gate_does_not_weaken_publish_auth(): void
    {
        config(['streaming.mediamtx.publish_secret' => 'church-secret']);
        [$stream] = $this->liveEvent(EventAccess::Private, 'secret123');

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
        ])->assertUnauthorized();

        $this->postJson('/api/mediamtx/auth', [
            'action' => 'publish',
            'path' => $stream->mediaPath(),
            'password' => 'church-secret',
        ])->assertOk();
    }

    /**
     * @return array{0: Stream, 1: Event}
     */
    private function liveEvent(EventAccess $access, ?string $password = null, bool $streamPublic = false): array
    {
        $org = Organization::query()->create([
            'name' => 'Private Org',
            'slug' => 'org-'.uniqid(),
            'is_public' => true,
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Live,
            'is_public' => $streamPublic || ($access === EventAccess::Public),
        ]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Locked Service',
            'status' => EventStatus::Live,
            'access' => $access,
            'access_password' => $password,
        ]);

        return [$stream, $event];
    }

    private function tokenFrom(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) ($query['media'] ?? '');
    }
}
