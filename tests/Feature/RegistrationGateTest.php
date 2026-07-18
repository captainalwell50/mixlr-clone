<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_hidden_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->get('/register')->assertNotFound();
    }

    public function test_post_register_rejected_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->post('/register', [
            'name' => 'Bot',
            'email' => 'bot@example.org',
            'password' => 'Password1!xx',
            'password_confirmation' => 'Password1!xx',
        ])->assertNotFound();
    }

    public function test_register_is_available_when_enabled(): void
    {
        config(['app.registration_enabled' => true]);

        $this->get('/register')->assertOk();
    }
}
