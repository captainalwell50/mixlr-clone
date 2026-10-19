<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_page_shows_mail_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Email', false)
            ->assertSee('Use Gmail SMTP', false)
            ->assertSee('Save email', false)
            ->assertSee('Send test email', false);
    }

    public function test_admin_can_save_gmail_smtp_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_username' => 'church@gmail.com',
                'mail_password' => 'abcd efgh ijkl mnop',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'church@gmail.com',
                'mail_from_name' => 'Sound Mix Live',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');

        $mail = SiteSetting::mailSettings();
        $this->assertTrue($mail['ready']);
        $this->assertSame('smtp', $mail['mailer']);
        $this->assertSame('smtp.gmail.com', $mail['host']);
        $this->assertSame(587, $mail['port']);
        $this->assertSame('church@gmail.com', $mail['username']);
        $this->assertSame('abcd efgh ijkl mnop', $mail['password']);
        $this->assertSame('tls', $mail['encryption']);
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('SMTP READY', false)
            ->assertSee('church@gmail.com', false)
            ->assertDontSee('abcd efgh ijkl mnop', false);
    }

    public function test_blank_password_keeps_existing_secret(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_username' => 'church@gmail.com',
                'mail_password' => 'keep-this-app-password',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'church@gmail.com',
                'mail_from_name' => 'Sound Mix Live',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_username' => 'church@gmail.com',
                'mail_password' => '',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'church@gmail.com',
                'mail_from_name' => 'Sound Mix Live',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('keep-this-app-password', SiteSetting::mailPassword());
    }

    public function test_admin_can_send_test_email(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_username' => 'church@gmail.com',
                'mail_password' => 'app-password',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'church@gmail.com',
                'mail_from_name' => 'Sound Mix Live',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->actingAs($admin)
            ->post(route('admin.settings.mail.test'))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');
    }

    public function test_non_admin_cannot_update_mail(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::query()->create(['name' => 'C', 'slug' => 'c-mail-smtp-'.uniqid()]);
        $org->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
            ])
            ->assertForbidden();
    }
}
