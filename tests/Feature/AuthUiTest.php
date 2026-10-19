<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_reworked_ui(): void
    {
        SiteSetting::putWhatsAppSupport('2348000000000', enabled: true);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Welcome back');
        $response->assertSee('Sign in to continue to SoundMix.');
        $response->assertSee('Email address');
        $response->assertSee('Password');
        $response->assertSee('Remember me');
        $response->assertSee('Forgot password?');
        $response->assertSee('Sign in');
        $response->assertSee('Create account');

        // Brand panel & features (neutral platform language)
        $response->assertSee('Broadcast live.', false);
        $response->assertSee('Publish content.', false);
        $response->assertSee('Engage your audience.', false);
        $response->assertSee('Built for live audio and digital content', false);
        $response->assertSee('Live audio broadcasting and web studio', false);
        $response->assertSee('Private events and controlled access', false);
        $response->assertSee('On-demand content archives and podcasts', false);
        $response->assertSee('Real-time audience interaction and engagement', false);
        $response->assertSee('name@example.com', false);
        $response->assertDontSee('paid events');
        $response->assertDontSee('Share teachings.');
        $response->assertDontSee('Ministry-Focused Platform');
        $response->assertDontSee('name@church.org');
        $response->assertSee('auth-brand-panel', false);

        // Password visibility toggle
        $response->assertSee('auth-password-toggle', false);
        $response->assertSee('aria-label="Show password"', false);

        // WhatsApp widget presence
        $response->assertSee('data-soundmix-wa-widget="public"', false);
    }

    public function test_login_authentication_and_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'pastor@grace.test',
            'password' => 'Secret123!xx',
        ]);

        // Failed attempt
        $failedResponse = $this->post(route('login'), [
            'email' => 'pastor@grace.test',
            'password' => 'WrongPassword!',
        ]);
        $failedResponse->assertSessionHasErrors('email');
        $this->assertGuest();

        // Successful attempt
        $successResponse = $this->post(route('login'), [
            'email' => 'pastor@grace.test',
            'password' => 'Secret123!xx',
        ]);
        $successResponse->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_forgot_password_page_renders_reworked_ui(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Forgot Password');
        $response->assertSee('Enter the email address connected to your SoundMix account and we\'ll send you a reset link.', false);
        $response->assertSee('Email address');
        $response->assertSee('Send reset link');
        $response->assertSee('Back to sign in');
        $response->assertSee('auth-brand-panel', false);
    }

    public function test_forgot_password_submission_does_not_reveal_account_existence(): void
    {
        $user = User::factory()->create(['email' => 'known@example.test']);

        // Known email
        $response = $this->post(route('password.email'), [
            'email' => 'known@example.test',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account exists for that email, a password reset link has been dispatched.');

        // Unknown email returns the same message
        $responseUnknown = $this->post(route('password.email'), [
            'email' => 'unknown@example.test',
        ]);
        $responseUnknown->assertRedirect();
        $responseUnknown->assertSessionHas('status', 'If an account exists for that email, a password reset link has been dispatched.');
    }

    public function test_reset_password_page_renders_reworked_ui_with_readonly_email(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.test']);
        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $response->assertOk();
        $response->assertSee('Set a new password');
        $response->assertSee('Choose a new password for your SoundMix account.');
        $response->assertSee('Email address');
        $response->assertSee('readonly', false);
        $response->assertSee('resetme@example.test');
        $response->assertSee('New password');
        $response->assertSee('Confirm new password');
        $response->assertSee('Reset password');
        $response->assertSee('Back to sign in');
        $response->assertDontSee('name@church.org');

        // Toggles for both password fields
        $response->assertSee('Show password confirmation', false);
    }

    public function test_reset_password_submission_updates_password(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.test']);
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'resetme@example.test',
            'password' => 'BrandNewPass1!xx',
            'password_confirmation' => 'BrandNewPass1!xx',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('BrandNewPass1!xx', $user->fresh()->password));
    }

    public function test_registration_page_renders_neutral_copy_and_placeholders(): void
    {
        config(['app.registration_enabled' => true]);

        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Start broadcasting');
        $response->assertSee('Create your creator account — then set up your broadcast channel and open Studio.', false);
        $response->assertSee('placeholder="Your name"', false);
        $response->assertSee('placeholder="name@example.com"', false);
        $response->assertDontSee('Pastor John Doe');
        $response->assertDontSee('name@church.org');
        $response->assertDontSee('church, radio, or event channel');
    }

    public function test_account_page_renders_dedicated_password_and_security_section(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.edit'));

        $response->assertOk();
        $response->assertSee('Password & Security');
        $response->assertSee('Current password');
        $response->assertSee('New password');
        $response->assertSee('Confirm new password');
        $response->assertSee('Update password');
        $response->assertSee('Creator Profile');
        $response->assertDontSee('Church Creator Profile');
    }
}
