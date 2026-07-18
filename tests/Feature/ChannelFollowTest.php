<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelFollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_and_unfollow_channel(): void
    {
        $org = Organization::query()->create([
            'name' => 'Demo',
            'slug' => 'demo',
            'is_public' => true,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('channels.follow', $org))
            ->assertRedirect();

        $this->assertTrue($user->fresh()->followsChannel($org));

        $this->actingAs($user)
            ->delete(route('channels.unfollow', $org))
            ->assertRedirect();

        $this->assertFalse($user->fresh()->followsChannel($org));
    }

    public function test_channel_page_shows_for_public_org(): void
    {
        $org = Organization::query()->create([
            'name' => 'Demo Church',
            'slug' => 'demo-church',
            'tagline' => 'Welcome',
            'is_public' => true,
        ]);

        $this->get(route('channels.show', $org))
            ->assertOk()
            ->assertSee('Demo Church', false)
            ->assertSee('Welcome', false);
    }
}
