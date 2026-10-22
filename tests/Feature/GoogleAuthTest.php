<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockGoogleUser(string $id, string $email, string $name, bool $verified = true, ?string $avatar = null): void
    {
        $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getAvatar')->andReturn($avatar ?? 'https://lh3.googleusercontent.com/avatar.jpg');
        $googleUser->user = [
            'email_verified' => $verified,
            'locale' => 'en',
        ];
        $googleUser->shouldReceive('getRaw')->andReturn([
            'email_verified' => $verified,
            'locale' => 'en',
        ]);

        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_redirect_initiates_oauth_and_stores_sanitized_intent(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('scopes')->with(['openid', 'profile', 'email'])->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google', [
            'intent' => 'listener',
            'intended' => '/listen/grace-stream',
        ]));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $this->assertEquals('listener', session('google_auth_intent'));
        $this->assertEquals('/listen/grace-stream', session('url.intended'));
    }

    public function test_google_redirect_rejects_open_redirect_urls(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // Disallowed targets: external domain, protocol relative, javascript scheme
        $this->get(route('auth.google', ['intended' => 'https://evil.com/phish']));
        $this->assertNull(session('url.intended'));

        $this->get(route('auth.google', ['intended' => '//evil.com/phish']));
        $this->assertNull(session('url.intended'));

        $this->get(route('auth.google', ['intended' => 'javascript:alert(1)']));
        $this->assertNull(session('url.intended'));
    }

    public function test_new_google_listener_creates_user_and_identity_without_organization(): void
    {
        $this->mockGoogleUser(
            id: 'google-sub-1001',
            email: 'newlistener@example.com',
            name: 'Sarah Connor',
            verified: true,
            avatar: 'https://lh3.googleusercontent.com/sarah.jpg'
        );

        $response = $this->withSession([
            'google_auth_intent' => 'listener',
            'google_auth_intended' => '/listen/test-stream',
        ])->get(route('auth.google.callback'));

        $response->assertRedirect('/listen/test-stream');

        $this->assertAuthenticated();
        $user = Auth::user();
        $this->assertNotNull($user);
        $this->assertEquals('Sarah Connor', $user->name);
        $this->assertEquals('newlistener@example.com', $user->email);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('https://lh3.googleusercontent.com/sarah.jpg', $user->avatar_path);
        $this->assertFalse($user->isAdmin());

        // Assert UserIdentity created
        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-1001',
            'provider_email' => 'newlistener@example.com',
        ]);

        $this->assertTrue($user->hasGoogleIdentity());
        $this->assertNotNull($user->googleIdentity());

        // Assert NO organizations or channels created for listener
        $this->assertEquals(0, $user->organizations()->count());
        $this->assertEquals(0, Organization::query()->count());
        $this->assertEquals(0, Stream::query()->count());
    }

    public function test_existing_verified_email_user_links_google_safely(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing Person',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => null,
        ]);

        $this->mockGoogleUser(
            id: 'google-sub-2002',
            email: 'EXISTING@EXAMPLE.COM', // Test case insensitivity / normalization
            name: 'Existing Person Google',
            verified: true,
            avatar: 'https://lh3.googleusercontent.com/existing.jpg'
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('discover'));

        $this->assertAuthenticatedAs($existing);
        $this->assertEquals(1, User::query()->where('email', 'existing@example.com')->count());

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $existing->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-2002',
            'provider_email' => 'existing@example.com',
        ]);

        // Email verified timestamp was set from verified Google confirmation
        $this->assertNotNull($existing->fresh()->email_verified_at);
        // Original local password is preserved
        $this->assertTrue(Hash::check('Secret123!', $existing->fresh()->password));
    }

    public function test_duplicate_callback_does_not_duplicate_user_or_identity(): void
    {
        $this->mockGoogleUser(
            id: 'google-sub-3003',
            email: 'repeat@example.com',
            name: 'Repeat Listener',
            verified: true
        );

        // First login
        $this->get(route('auth.google.callback'))->assertRedirect(route('discover'));
        $initialUserId = Auth::id();

        Auth::logout();

        // Second login with identical Google identity
        $this->mockGoogleUser(
            id: 'google-sub-3003',
            email: 'repeat@example.com',
            name: 'Repeat Listener',
            verified: true
        );

        $this->get(route('auth.google.callback'))->assertRedirect(route('discover'));

        $this->assertEquals($initialUserId, Auth::id());
        $this->assertEquals(1, User::query()->where('email', 'repeat@example.com')->count());
        $this->assertEquals(1, UserIdentity::query()->where('provider_user_id', 'google-sub-3003')->count());
    }

    public function test_unverified_provider_email_cannot_hijack_existing_account(): void
    {
        $existing = User::factory()->create([
            'email' => 'target@example.com',
            'name' => 'Target Account',
            'password' => Hash::make('ValidPass123!'),
        ]);

        $this->mockGoogleUser(
            id: 'google-sub-evil-999',
            email: 'target@example.com',
            name: 'Attacker Fake',
            verified: false // Unverified provider email!
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('user_identities', [
            'provider_user_id' => 'google-sub-evil-999',
        ]);
    }

    public function test_intended_listen_and_event_urls_restored(): void
    {
        $this->mockGoogleUser(
            id: 'google-sub-4004',
            email: 'listener4@example.com',
            name: 'Listener 4',
            verified: true
        );

        $response = $this->withSession([
            'google_auth_intended' => '/e/sunday-broadcast',
        ])->get(route('auth.google.callback'));

        $response->assertRedirect('/e/sunday-broadcast');
    }

    public function test_creator_intent_redirects_to_onboarding_for_new_creator(): void
    {
        $this->mockGoogleUser(
            id: 'google-sub-5005',
            email: 'creator5@example.com',
            name: 'Pastor John',
            verified: true
        );

        $response = $this->withSession([
            'google_auth_intent' => 'creator',
        ])->get(route('auth.google.callback'));

        $response->assertRedirect(route('onboarding.show'));

        $user = Auth::user();
        $this->assertEquals(0, $user->organizations()->count());
    }

    public function test_passwordless_oauth_user_cannot_log_in_via_password_form_without_password(): void
    {
        $user = User::factory()->create([
            'email' => 'googleonly@example.com',
            'password' => null,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'googleonly@example.com',
            'password' => 'attempted-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_existing_email_password_login_still_works(): void
    {
        $user = User::factory()->create([
            'email' => 'traditional@example.com',
            'password' => Hash::make('CorrectPassword1!'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'traditional@example.com',
            'password' => 'CorrectPassword1!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_dashboard_redirects_listener_without_org_to_discover_instead_of_onboarding(): void
    {
        $listener = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($listener)->get(route('dashboard'));

        $response->assertRedirect(route('discover'));
    }

    public function test_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_google_drive_integration_config_remains_separate_and_intact(): void
    {
        $driveConfig = config('services.google_drive');
        $authConfig = config('services.google');

        $this->assertIsArray($driveConfig);
        $this->assertIsArray($authConfig);

        $this->assertStringContainsString('integrations/google-drive/callback', $driveConfig['redirect']);
        $this->assertStringContainsString('auth/google/callback', $authConfig['redirect']);

        $this->assertContains('https://www.googleapis.com/auth/drive.file', $driveConfig['scopes']);
        $this->assertEquals(['openid', 'profile', 'email'], $authConfig['scopes']);
    }

    public function test_connected_accounts_rendered_in_account_settings(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);

        UserIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-777',
            'provider_email' => 'connected@gmail.com',
        ]);

        $response = $this->actingAs($user)->get(route('account.edit'));

        $response->assertOk();
        $response->assertSee('Connected Accounts');
        $response->assertSee('Connected (connected@gmail.com)');
        $response->assertSee('Your account is authenticated via Google');
    }

    public function test_passwordless_user_can_set_first_password_without_current_password(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'current_password' => '',
            'password' => 'NewSecurePassword1!x',
            'password_confirmation' => 'NewSecurePassword1!x',
        ]);

        $response->assertRedirect(route('account.edit'));
        $this->assertTrue(Hash::check('NewSecurePassword1!x', $user->fresh()->password));
    }

    public function test_session_regenerated_after_google_login(): void
    {
        $this->mockGoogleUser(
            id: 'google-sub-session-1',
            email: 'sessiontest@example.com',
            name: 'Session Test',
            verified: true
        );

        // Start session
        $this->get('/login');
        $initialSessionId = session()->getId();

        $this->get(route('auth.google.callback'));

        $this->assertNotEquals($initialSessionId, session()->getId());
        $this->assertAuthenticated();
    }

    public function test_existing_creator_email_signup_still_works(): void
    {
        config(['app.registration_enabled' => true]);
        config(['registration.min_form_seconds' => 0]);

        $response = $this->withSession(['registration_started_at' => time() - 10])
            ->post(route('register'), [
                'name' => 'Creator Email Test',
                'email' => 'creatoremail@example.com',
                'password' => 'SecurePass123!x',
                'password_confirmation' => 'SecurePass123!x',
                'website' => '',
            ]);

        $response->assertRedirect(route('onboarding.show'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'creatoremail@example.com']);
    }

    public function test_guest_public_listening_still_accessible_without_auth(): void
    {
        $org = Organization::query()->create(['name' => 'Public Org', 'slug' => 'public-org']);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Sunday Service',
            'is_public' => true,
        ]);

        $response = $this->get(route('listen.stream', $stream));

        $response->assertOk();
        $response->assertSee('Sunday Service');
        $response->assertSee('listener-auth-sheet', false);
    }

    public function test_listen_page_renders_auth_sheet_and_chat_composer_for_guests(): void
    {
        $org = Organization::query()->create(['name' => 'Chat Church', 'slug' => 'chat-church']);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Live Praise',
            'is_public' => true,
            'chat_enabled' => true,
        ]);

        $response = $this->get(route('listen.stream', $stream));

        $response->assertOk();
        $response->assertSee('data-authenticated="0"', false);
        $response->assertSee('Sign in to comment', false);
        $response->assertSee('listener-auth-sheet', false);
        $response->assertSee('Continue with Google', false);
    }

    public function test_admin_account_retains_privileges_when_linking_google(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@soundmix.live',
            'is_admin' => true,
            'password' => Hash::make('AdminPass123!'),
        ]);

        $this->mockGoogleUser(
            id: 'google-sub-admin-1',
            email: 'admin@soundmix.live',
            name: 'Platform Admin',
            verified: true
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_existing_user_linking_denied_when_google_email_unverified(): void
    {
        $existing = User::factory()->create([
            'email' => 'unverifiedtarget@example.com',
            'name' => 'Original Name',
            'password' => Hash::make('SecurePassword123!'),
            'email_verified_at' => now(),
        ]);

        $this->mockGoogleUser(
            id: 'google-sub-unverified-1',
            email: 'unverifiedtarget@example.com',
            name: 'Malicious Impersonator',
            verified: false // Google did not verify email
        );

        $response = $this->get(route('auth.google.callback'));

        // Denied: redirected to login with error
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');

        // Unauthenticated
        $this->assertGuest();

        // No UserIdentity created
        $this->assertDatabaseMissing('user_identities', [
            'provider_user_id' => 'google-sub-unverified-1',
        ]);

        // Existing account remains completely untouched
        $fresh = $existing->fresh();
        $this->assertEquals('Original Name', $fresh->name);
        $this->assertTrue(Hash::check('SecurePassword123!', $fresh->password));
        $this->assertNull($fresh->googleIdentity());
        $this->assertEquals(1, User::query()->where('email', 'unverifiedtarget@example.com')->count());
    }

    public function test_existing_user_linking_succeeds_when_google_email_verified(): void
    {
        $existing = User::factory()->create([
            'email' => 'verifiedtarget@example.com',
            'name' => 'Original Name',
            'password' => Hash::make('SecurePassword123!'),
            'email_verified_at' => null,
        ]);

        $this->mockGoogleUser(
            id: 'google-sub-verified-1',
            email: 'verifiedtarget@example.com',
            name: 'Google Verified User',
            verified: true // Verified email claim from Google
        );

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('discover'));
        $this->assertAuthenticatedAs($existing);

        // Exactly one user exists (no duplicate)
        $this->assertEquals(1, User::query()->where('email', 'verifiedtarget@example.com')->count());

        // Identity created and linked
        $this->assertDatabaseHas('user_identities', [
            'user_id' => $existing->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-verified-1',
            'provider_email' => 'verifiedtarget@example.com',
        ]);

        $fresh = $existing->fresh();
        $this->assertTrue($fresh->hasGoogleIdentity());
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertTrue(Hash::check('SecurePassword123!', $fresh->password));
    }

    public function test_password_user_must_provide_correct_current_password_to_change_it(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentSecret123!'),
        ]);

        // 1. Missing current password fails
        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'current_password' => '',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('CurrentSecret123!', $user->fresh()->password));

        // 2. Incorrect current password fails
        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'current_password' => 'WrongPassword999!',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('CurrentSecret123!', $user->fresh()->password));

        // 3. Correct current password succeeds
        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'current_password' => 'CurrentSecret123!',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);
        $response->assertRedirect(route('account.edit'));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('BrandNewPassword123!', $user->fresh()->password));
    }

    public function test_oauth_only_user_lifecycle_first_password_future_changes_and_disconnect_protection(): void
    {
        // 1. OAuth user created with password=null
        $user = User::factory()->create([
            'email' => 'oauthuser@example.com',
            'password' => null,
        ]);
        $identity = UserIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'sub-oauth-lifecycle',
            'provider_email' => 'oauthuser@example.com',
        ]);

        $this->assertFalse($user->hasPassword());
        $this->assertTrue($user->hasGoogleIdentity());

        // 2. Disconnecting Google while password is null is strictly BLOCKED
        $disconnectResponse = $this->actingAs($user)->delete(route('account.identities.destroy', 'google'));
        $disconnectResponse->assertRedirect(route('account.edit'));
        $disconnectResponse->assertSessionHas('error');
        $this->assertDatabaseHas('user_identities', ['id' => $identity->id]);

        // 3. OAuth-only user can establish first password WITHOUT current_password
        $setupResponse = $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'current_password' => '',
            'password' => 'FirstEverPassword123!',
            'password_confirmation' => 'FirstEverPassword123!',
        ]);
        $setupResponse->assertRedirect(route('account.edit'));
        $setupResponse->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasPassword());
        $this->assertTrue(Hash::check('FirstEverPassword123!', $fresh->password));

        // 4. Now that password exists, future changes REQUIRE current_password
        $subsequentWithoutCurrent = $this->actingAs($fresh)->put(route('account.update'), [
            'name' => $fresh->name,
            'current_password' => '',
            'password' => 'SecondPassword123!x',
            'password_confirmation' => 'SecondPassword123!x',
        ]);
        $subsequentWithoutCurrent->assertSessionHasErrors('current_password');

        // Succeeded with correct current password
        $subsequentWithCurrent = $this->actingAs($fresh)->put(route('account.update'), [
            'name' => $fresh->name,
            'current_password' => 'FirstEverPassword123!',
            'password' => 'SecondPassword123!x',
            'password_confirmation' => 'SecondPassword123!x',
        ]);
        $subsequentWithCurrent->assertRedirect(route('account.edit'));
        $this->assertTrue(Hash::check('SecondPassword123!x', $user->fresh()->password));

        // 5. Account remains accessible after password establishment:
        // A. Via standard email/password login
        Auth::logout();
        $loginResponse = $this->post(route('login'), [
            'email' => 'oauthuser@example.com',
            'password' => 'SecondPassword123!x',
        ]);
        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // B. Via Google OAuth login
        Auth::logout();
        $this->mockGoogleUser(
            id: 'sub-oauth-lifecycle',
            email: 'oauthuser@example.com',
            name: 'OAuth User',
            verified: true
        );
        $this->get(route('auth.google.callback'))->assertRedirect(route('discover'));
        $this->assertAuthenticatedAs($user);

        // C. Can now safely disconnect Google because user has a password
        $allowedDisconnect = $this->actingAs($user)->delete(route('account.identities.destroy', 'google'));
        $allowedDisconnect->assertRedirect(route('account.edit'));
        $allowedDisconnect->assertSessionHas('status');
        $this->assertDatabaseMissing('user_identities', ['id' => $identity->id]);
    }

    public function test_authenticated_user_can_connect_google_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'standard@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $this->mockGoogleUser(
            id: 'google-sub-connect-888',
            email: 'standard.work@gmail.com',
            name: 'Standard Google',
            verified: true
        );

        // User initiates connect while logged in
        $this->actingAs($user)->get(route('auth.google', ['intended' => route('account.edit')]));

        // Callback completes
        $response = $this->actingAs($user)->get(route('auth.google.callback'));

        $response->assertRedirect(route('account.edit'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-connect-888',
            'provider_email' => 'standard.work@gmail.com',
        ]);

        $this->assertTrue($user->fresh()->hasGoogleIdentity());
    }
}
