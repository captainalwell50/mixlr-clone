<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_own_account_on_web(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'is_admin' => false,
        ]);
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
            'is_public' => true,
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->from(route('account.edit'))
            ->delete(route('account.destroy'), [
                'password' => 'password',
                'confirm' => 'DELETE',
            ])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('organizations', ['id' => $org->id]);
        $this->assertDatabaseMissing('organization_user', [
            'user_id' => $user->id,
            'organization_id' => $org->id,
        ]);
    }

    public function test_last_admin_cannot_self_delete(): void
    {
        $admin = User::factory()->create([
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('account.edit'))
            ->delete(route('account.destroy'), [
                'password' => 'password',
                'confirm' => 'DELETE',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_api_can_delete_account_with_password(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/account', [
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_api_rejects_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/account', [
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
