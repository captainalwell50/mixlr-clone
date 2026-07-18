<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'), false);
        $response->assertSee('When the room gathers', false);
        $response->assertDontSee('From link to live stage in three beats', false);
    }

    public function test_how_it_works_page_is_available(): void
    {
        $response = $this->get(route('how-it-works'));

        $response->assertOk();
        $response->assertSee('How it works', false);
        $response->assertSee('Schedule the event', false);
        $response->assertSee('Go live from Studio', false);
    }
}
