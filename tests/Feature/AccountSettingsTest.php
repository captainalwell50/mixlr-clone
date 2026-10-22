<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use App\Services\OutboundEmailService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'creator@soundmix.test',
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect(route('account.edit'));
        $response->assertSessionHas('status', __('Profile updated successfully.'));
        $this->assertEquals('Updated Name', $user->fresh()->name);
    }

    public function test_updated_name_persists_after_reload(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Creator',
        ]);

        $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Persisted Creator',
        ]);

        $user->refresh();
        $this->assertEquals('Persisted Creator', $user->name);

        $pageResponse = $this->actingAs($user)->get(route('account.edit'));
        $pageResponse->assertOk();
        $pageResponse->assertSee('value="Persisted Creator"', false);
    }

    public function test_email_update_works_if_supported(): void
    {
        $user = User::factory()->create([
            'name' => 'Email Tester',
            'email' => 'oldemail@soundmix.test',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Email Tester',
            'email' => 'NewEmail@SoundMix.test ',
        ]);

        $response->assertRedirect(route('account.edit'));
        $fresh = $user->fresh();
        $this->assertEquals('newemail@soundmix.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_duplicate_email_rejected(): void
    {
        User::factory()->create([
            'email' => 'existing@soundmix.test',
        ]);

        $user = User::factory()->create([
            'name' => 'Second User',
            'email' => 'second@soundmix.test',
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Second User',
            'email' => 'existing@soundmix.test',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals('second@soundmix.test', $user->fresh()->email);
    }

    public function test_current_password_required_for_password_change(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentSecret123!'),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue(Hash::check('CurrentSecret123!', $user->fresh()->password));
    }

    public function test_wrong_current_password_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'WrongPassword999!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue(Hash::check('CorrectPassword123!', $user->fresh()->password));
    }

    public function test_password_confirmation_required(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'CorrectPassword123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'Mismatching123!',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertTrue(Hash::check('CorrectPassword123!', $user->fresh()->password));
    }

    public function test_valid_password_change_succeeds(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => Hash::make('OldSecret123!'),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'OldSecret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $response->assertRedirect(route('account.edit'));
        $response->assertSessionHas('status', __('Account updated. Your new password is active.'));
        $this->assertTrue(Hash::check('NewSecret123!', $user->fresh()->password));

        Mail::assertQueued(PasswordChangedMail::class);
    }

    public function test_new_password_works_for_login(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'loginuser@soundmix.test',
            'password' => Hash::make('OldSecret123!'),
        ]);

        $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'OldSecret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $this->assertTrue(Auth::guard('web')->attempt([
            'email' => 'loginuser@soundmix.test',
            'password' => 'NewSecret123!',
        ]));
    }

    public function test_old_password_stops_working(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'loginuser2@soundmix.test',
            'password' => Hash::make('OldSecret123!'),
        ]);

        $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'OldSecret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $this->assertFalse(Auth::guard('web')->attempt([
            'email' => 'loginuser2@soundmix.test',
            'password' => 'OldSecret123!',
        ]));
    }

    public function test_user_remains_safely_authenticated_after_change(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => Hash::make('OldSecret123!'),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'OldSecret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $response->assertRedirect(route('account.edit'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_changed_mail_dispatch_does_not_corrupt_persistence(): void
    {
        $mockOutbound = Mockery::mock(OutboundEmailService::class);
        $mockOutbound->shouldReceive('sendTransactional')
            ->once()
            ->andThrow(new Exception('SMTP connection timed out'));
        $this->app->instance(OutboundEmailService::class, $mockOutbound);

        $user = User::factory()->create([
            'password' => Hash::make('OldSecret123!'),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'current_password' => 'OldSecret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        // Request does not blow up with 500, user is redirected and password IS persisted
        $response->assertRedirect(route('account.edit'));
        $this->assertTrue(Hash::check('NewSecret123!', $user->fresh()->password));
    }

    public function test_validation_errors_display_correctly(): void
    {
        $user = User::factory()->create();

        // Submit invalid name
        $response = $this->actingAs($user)
            ->from(route('account.edit'))
            ->put(route('account.update'), [
                'name' => '',
            ]);

        $response->assertRedirect(route('account.edit'));
        $response->assertSessionHasErrors(['name']);

        // Check error renders in the view
        $pageResponse = $this->actingAs($user)->get(route('account.edit'));
        $pageResponse->assertSee('The name field is required.');
    }

    public function test_account_forms_contain_correct_actions_and_methods(): void
    {
        $user = User::factory()->create([
            'name' => 'Form Checker',
        ]);

        $response = $this->actingAs($user)->get(route('account.edit'));
        $response->assertOk();

        $content = $response->getContent();

        // Profile Form
        $this->assertStringContainsString('action="'.route('account.update').'"', $content);
        $this->assertStringContainsString('name="name"', $content);
        $this->assertStringContainsString('value="Form Checker"', $content);
        $this->assertStringContainsString('Save profile', $content);

        // Password Form
        $this->assertStringContainsString('name="current_password"', $content);
        $this->assertStringContainsString('name="password"', $content);
        $this->assertStringContainsString('name="password_confirmation"', $content);
        $this->assertStringContainsString('Update password', $content);

        // Delete Account Form
        $this->assertStringContainsString('action="'.route('account.destroy').'"', $content);
        $this->assertStringContainsString('name="confirm"', $content);
        $this->assertStringContainsString('Delete my account', $content);

        // Check no nested forms: count of <form matches count of </form>
        $openCount = substr_count($content, '<form');
        $closeCount = substr_count($content, '</form>');
        $this->assertEquals($openCount, $closeCount);
        $this->assertGreaterThanOrEqual(3, $openCount);
    }

    public function test_non_user_cannot_update_another_account(): void
    {
        // Unauthenticated
        $response = $this->put(route('account.update'), [
            'name' => 'Intruder',
        ]);
        $response->assertRedirect(route('login'));

        // Authenticated user A trying to target user B by passing extra user parameters
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        $responseA = $this->actingAs($userA)->put(route('account.update'), [
            'id' => $userB->id,
            'user_id' => $userB->id,
            'name' => 'Hacked Name',
        ]);

        $responseA->assertRedirect(route('account.edit'));

        // User A was modified, User B was untouched
        $this->assertEquals('Hacked Name', $userA->fresh()->name);
        $this->assertEquals('User B', $userB->fresh()->name);
    }

    public function test_existing_light_dark_account_ui_remains_intact(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.edit'));
        $response->assertOk();

        // Verify dark theme classes and layout components remain intact
        $response->assertSee('dark:border-zinc-800');
        $response->assertSee('dark:bg-zinc-900/50');
        $response->assertSee('Interface Appearance');
        $response->assertSee('Danger Zone — Delete Account');
    }
}
