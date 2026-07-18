<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_can_manage_own_streams_not_others(): void
    {
        $orgA = Organization::query()->create(['name' => 'A', 'slug' => 'a']);
        $orgB = Organization::query()->create(['name' => 'B', 'slug' => 'b']);
        $streamA = Stream::query()->create([
            'organization_id' => $orgA->id,
            'uuid' => fake()->uuid(),
            'title' => 'A Stream',
            'status' => StreamStatus::Offline,
        ]);
        $streamB = Stream::query()->create([
            'organization_id' => $orgB->id,
            'uuid' => fake()->uuid(),
            'title' => 'B Stream',
            'status' => StreamStatus::Offline,
        ]);

        $user = User::factory()->create(['is_admin' => false]);
        $orgA->users()->attach($user->id, ['role' => OrgRole::Admin->value]);

        $this->actingAs($user)
            ->get(route('admin.streams.edit', $streamA))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.streams.edit', $streamB))
            ->assertForbidden();
    }

    public function test_platform_admin_can_add_members(): void
    {
        $org = Organization::query()->create(['name' => 'C', 'slug' => 'c']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.organizations.members.store', $org), [
                'email' => 'volunteer@example.com',
                'name' => 'Volunteer',
                'role' => OrgRole::Member->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'volunteer@example.com',
        ]);
    }
}
