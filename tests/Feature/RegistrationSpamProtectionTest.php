<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationSpamProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.registration_enabled' => true,
            'registration.min_form_seconds' => 0,
            'registration.require_verification' => false,
            'registration.block_disposable_emails' => true,
            'registration.turnstile.site_key' => null,
            'registration.turnstile.secret_key' => null,
        ]);
    }

    public function test_normal_register_still_works_when_enabled(): void
    {
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'Church Admin',
            'email' => 'church.admin@example.org',
            'password' => 'Password1!xx',
            'password_confirmation' => 'Password1!xx',
            'website' => '',
        ])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'church.admin@example.org',
            'name' => 'Church Admin',
        ]);
    }

    public function test_honeypot_rejection(): void
    {
        $this->get('/register')->assertOk();

        $this->from('/register')->post('/register', [
            'name' => 'Church Admin',
            'email' => 'bot@example.org',
            'password' => 'Password1!xx',
            'password_confirmation' => 'Password1!xx',
            'website' => 'http://spam.example',
        ])->assertRedirect('/register')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.org']);
    }

    public function test_spammy_name_rejection(): void
    {
        $this->get('/register')->assertOk();

        $this->from('/register')->post('/register', [
            'name' => 'xK9mP2qL8nR4zQ7',
            'email' => 'gibberish@example.org',
            'password' => 'Password1!xx',
            'password_confirmation' => 'Password1!xx',
            'website' => '',
        ])->assertRedirect('/register')->assertSessionHasErrors('name');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'gibberish@example.org']);
    }

    public function test_disposable_email_rejection(): void
    {
        $this->get('/register')->assertOk();

        $this->from('/register')->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'temp@mailinator.com',
            'password' => 'Password1!xx',
            'password_confirmation' => 'Password1!xx',
            'website' => '',
        ])->assertRedirect('/register')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_register_is_rate_limited(): void
    {
        // Invalid confirmation so attempts stay as guests while still hitting throttle.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => 'Church Admin',
                'email' => "user{$i}@example.org",
                'password' => 'Password1!xx',
                'password_confirmation' => 'mismatch',
                'website' => '',
            ]);
        }

        $this->post('/register', [
            'name' => 'Church Admin',
            'email' => 'user5@example.org',
            'password' => 'Password1!xx',
            'password_confirmation' => 'mismatch',
            'website' => '',
        ])->assertStatus(429);

        $this->assertSame(0, User::query()->count());
    }
}
