<?php

namespace Tests\Feature;

use App\Mail\AdminTestMail;
use App\Models\AdminMailAudit;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MailConfigurationService;
use App\Services\OutboundEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Crypt;
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

        $this->actingAs($user)
            ->post(route('admin.settings.mail.test'))
            ->assertForbidden();
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

    public function test_admin_can_save_provider_agnostic_smtp_settings_with_reply_to(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_enabled' => '1',
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp-relay.brevo.com',
                'mail_port' => 587,
                'mail_username' => 'brevo-user-account',
                'mail_password' => 'secret-brevo-key-9988',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'notifications@soundmix.live',
                'mail_from_name' => 'SoundMix Broadcasts',
                'mail_reply_to' => 'support@soundmix.live',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');

        $mail = SiteSetting::mailSettings();
        $this->assertTrue($mail['enabled']);
        $this->assertTrue($mail['ready']);
        $this->assertSame('smtp', $mail['mailer']);
        $this->assertSame('smtp-relay.brevo.com', $mail['host']);
        $this->assertSame(587, $mail['port']);
        $this->assertSame('notifications@soundmix.live', $mail['from_address']);
        $this->assertSame('support@soundmix.live', $mail['reply_to']);
        $this->assertSame('support@soundmix.live', config('mail.reply_to.address'));
    }

    public function test_smtp_secret_is_stored_encrypted_in_database(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'mail.soundmix.live',
                'mail_port' => 465,
                'mail_username' => 'smtp-admin',
                'mail_password' => 'super-secret-password-xyz',
                'mail_encryption' => 'ssl',
                'mail_from_address' => 'system@soundmix.live',
                'mail_from_name' => 'SoundMix System',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $storedRaw = SiteSetting::query()->where('key', SiteSetting::KEY_MAIL_PASSWORD)->value('value');
        $this->assertNotNull($storedRaw);
        $this->assertNotSame('super-secret-password-xyz', $storedRaw);
        $this->assertSame('super-secret-password-xyz', decrypt($storedRaw));
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

    public function test_new_password_replaces_existing_secret(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_username' => 'church@gmail.com',
                'mail_password' => 'initial-secret',
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
                'mail_password' => 'updated-new-secret',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'church@gmail.com',
                'mail_from_name' => 'Sound Mix Live',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('updated-new-secret', SiteSetting::mailPassword());
    }

    public function test_mail_password_can_be_cleared(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_username' => 'church@gmail.com',
                'mail_password' => 'secret-to-remove',
                'mail_encryption' => 'tls',
            ]);

        $this->assertSame('secret-to-remove', SiteSetting::mailPassword());

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
                'mail_clear_password' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertNull(SiteSetting::mailPassword());
    }

    public function test_live_smtp_can_be_enabled_and_disabled_via_killswitch(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. Enable live delivery
        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_enabled' => '1',
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.postmarkapp.com',
                'mail_port' => 587,
                'mail_username' => 'postmark-token',
                'mail_password' => 'postmark-token',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'alerts@soundmix.live',
                'mail_from_name' => 'SoundMix Alerts',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertTrue(SiteSetting::mailEnabled());
        $this->assertSame('smtp', config('mail.default'));

        // 2. Disable live delivery (forces log mode killswitch)
        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_enabled' => '0',
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.postmarkapp.com',
                'mail_port' => 587,
                'mail_username' => 'postmark-token',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'alerts@soundmix.live',
                'mail_from_name' => 'SoundMix Alerts',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertFalse(SiteSetting::mailEnabled());
        $this->assertSame('log', config('mail.default'));
    }

    public function test_admin_can_send_test_email_to_custom_recipient(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_enabled' => '1',
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
            ->post(route('admin.settings.mail.test'), [
                'test_recipient' => 'diagnostics@destination-domain.org',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');

        Mail::assertSent(AdminTestMail::class, function (AdminTestMail $mail): bool {
            return $mail->hasTo('diagnostics@destination-domain.org');
        });

        $this->assertSame('success', SiteSetting::getValue(SiteSetting::KEY_MAIL_LAST_TEST_STATUS));
        $this->assertSame('diagnostics@destination-domain.org', SiteSetting::getValue(SiteSetting::KEY_MAIL_LAST_TEST_RECIPIENT));
    }

    public function test_smtp_failure_is_sanitized_and_does_not_leak_credentials(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        // Configure a secret password
        SiteSetting::putValue(SiteSetting::KEY_MAIL_ENABLED, '1');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_MAILER, 'smtp');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_HOST, '127.0.0.1');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_PORT, '2525');
        SiteSetting::putMailPassword('leaked-secret-credential-value-999');

        $mailConfig = app(MailConfigurationService::class);
        $result = $mailConfig->sendTest('test-dest@example.com', $admin);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertStringNotContainsString('leaked-secret-credential-value-999', $result['error']);
        $this->assertSame('failure', SiteSetting::getValue(SiteSetting::KEY_MAIL_LAST_TEST_STATUS));
    }

    public function test_admin_mail_audit_records_changes_and_never_leaks_secrets(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_enabled' => '1',
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.provider.test',
                'mail_port' => 587,
                'mail_username' => 'test-user',
                'mail_password' => 'super-top-secret-password-12345',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'sender@soundmix.live',
            ]);

        $audit = AdminMailAudit::query()->where('action', AdminMailAudit::ACTION_SETTINGS_UPDATED)->latest()->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('success', $audit->status);

        $jsonDetails = json_encode($audit->details);
        $this->assertStringNotContainsString('super-top-secret-password-12345', $jsonDetails);
        $this->assertSame('updated', $audit->details['password_action']);
    }

    public function test_password_reset_controller_uses_runtime_smtp_settings(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'member@soundmix.live']);

        SiteSetting::putValue(SiteSetting::KEY_MAIL_ENABLED, '1');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_MAILER, 'smtp');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_HOST, 'smtp.runtime.test');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_PORT, '587');
        SiteSetting::putMailPassword('dummy-pass');

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionHas('status');

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.runtime.test', config('mail.mailers.smtp.host'));
    }

    public function test_email_verification_controller_uses_runtime_smtp_settings(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'unverified@soundmix.live',
            'email_verified_at' => null,
        ]);

        SiteSetting::putValue(SiteSetting::KEY_MAIL_ENABLED, '1');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_MAILER, 'smtp');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_HOST, 'smtp.runtime.test');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_PORT, '587');
        SiteSetting::putMailPassword('dummy-pass');

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertSessionHas('status');

        $this->assertSame('smtp', config('mail.default'));
    }

    public function test_queued_job_processing_listener_dynamically_loads_latest_smtp_settings(): void
    {
        config(['mail.default' => 'log']);
        $this->assertSame('log', config('mail.default'));

        // Admin updates settings in database while worker is already running
        SiteSetting::putValue(SiteSetting::KEY_MAIL_ENABLED, '1');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_MAILER, 'smtp');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_HOST, 'smtp.worker-reload.test');
        SiteSetting::putValue(SiteSetting::KEY_MAIL_PORT, '587');
        SiteSetting::putMailPassword('worker-secret');

        // Simulate queue worker processing a job
        $mockJob = $this->createMock(\Illuminate\Contracts\Queue\Job::class);
        event(new JobProcessing('database', $mockJob));

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.worker-reload.test', config('mail.mailers.smtp.host'));
    }

    public function test_cloudflare_inbound_settings_remain_unaffected(): void
    {
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'inbound-dest@soundmix.live');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.mail'), [
                'mail_mailer' => 'log',
                'mail_encryption' => 'tls',
            ]);

        $this->assertSame('inbound-dest@soundmix.live', SiteSetting::emailRoutingActiveDestination());
    }
}
