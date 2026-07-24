<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_create_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Users');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Creator',
                'email' => 'creator@example.test',
                'password' => 'Password1!xx',
                'password_confirmation' => 'Password1!xx',
                'is_admin' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'creator@example.test',
            'is_admin' => 0,
        ]);
    }

    public function test_admin_can_set_password_and_non_admin_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'member@example.test']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'FreshPass9!',
                'password_confirmation' => 'FreshPass9!',
                'is_admin' => '0',
            ])
            ->assertRedirect(route('admin.users.edit', $user));

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('FreshPass9!', $user->password));

        $this->actingAs($user)
            ->withoutMiddleware(\App\Http\Middleware\EnsureOnboarded::class)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_user_can_change_own_password_on_account_page(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPass1!xx',
        ]);

        $this->actingAs($user)
            ->put(route('account.update'), [
                'name' => $user->name,
                'current_password' => 'OldPass1!xx',
                'password' => 'NewPass2!yy',
                'password_confirmation' => 'NewPass2!yy',
            ])
            ->assertRedirect(route('account.edit'));

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass2!yy', $user->fresh()->password));
    }
}
