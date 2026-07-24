<?php

namespace Tests\Feature;

use Tests\TestCase;

class DownloadsPageTest extends TestCase
{
    public function test_downloads_page_shows_mobile_and_desktop_stores(): void
    {
        $this->get(route('downloads'))
            ->assertOk()
            ->assertSee('Get Sound Mix Live')
            ->assertSee('Android APK')
            ->assertSee('Google Play')
            ->assertSee('App Store')
            ->assertSee('Download for')
            ->assertSee('Mac')
            ->assertSee('Microsoft')
            ->assertSee('Mobile')
            ->assertSee('Desktop');
    }
}
