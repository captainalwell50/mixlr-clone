<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_privacy_terms_and_support_are_public(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Microphone')
            ->assertSee('MediaMTX')
            ->assertSee('Profile → Delete account');

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Account deletion');

        $this->get(route('legal.support'))
            ->assertOk()
            ->assertSee('Support')
            ->assertSee(config('app.support_email'));
    }

    public function test_marketing_footer_links_legal_pages(): void
    {
        $this->get(route('downloads'))
            ->assertOk()
            ->assertSee(route('legal.privacy', absolute: false))
            ->assertSee(route('legal.terms', absolute: false))
            ->assertSee(route('legal.support', absolute: false));
    }
}
