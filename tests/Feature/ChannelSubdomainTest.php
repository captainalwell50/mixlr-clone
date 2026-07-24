<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelSubdomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_url_uses_path_when_subdomains_disabled(): void
    {
        config([
            'app.channel_subdomains' => false,
            'app.url' => 'https://soundmix.live',
        ]);

        $org = Organization::query()->create([
            'name' => 'Dunamis',
            'slug' => 'dunamistv-radio',
            'is_public' => true,
        ]);

        $this->assertStringEndsWith('/c/dunamistv-radio', $org->channelUrl());
        $this->assertStringNotContainsString('dunamistv-radio.soundmix', $org->channelUrl());
    }

    public function test_channel_url_uses_subdomain_when_enabled(): void
    {
        config([
            'app.channel_subdomains' => true,
            'app.channel_domain' => 'soundmix.live',
            'app.url' => 'https://soundmix.live',
        ]);

        $org = Organization::query()->create([
            'name' => 'Dunamis',
            'slug' => 'dunamistv-radio',
            'is_public' => true,
        ]);

        $this->assertSame('https://dunamistv-radio.soundmix.live', $org->channelUrl());
    }

    public function test_caddy_ask_allows_existing_channel(): void
    {
        Organization::query()->create([
            'name' => 'Dunamis',
            'slug' => 'dunamistv-radio',
            'is_public' => false,
        ]);

        config(['app.channel_domain' => 'soundmix.live']);

        $this->get('/internal/caddy-ask?domain=dunamistv-radio.soundmix.live')
            ->assertOk();

        $this->get('/internal/caddy-ask?domain=missing.soundmix.live')
            ->assertNotFound();

        $this->get('/internal/caddy-ask?domain=www.soundmix.live')
            ->assertNotFound();
    }
}
