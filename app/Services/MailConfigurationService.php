<?php

namespace App\Services;

use App\Mail\AdminTestMail;
use App\Models\AdminMailAudit;
use App\Models\EmailDelivery;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailConfigurationService
{
    /**
     * Get the consolidated mail settings with safe masked secrets.
     */
    public function getSettings(): array
    {
        $enabled = SiteSetting::mailEnabled();
        $storedMailer = SiteSetting::getValue(SiteSetting::KEY_MAIL_MAILER);
        $mailer = in_array($storedMailer, ['smtp', 'log'], true)
            ? $storedMailer
            : (string) config('mail.default', 'log');

        $host = SiteSetting::getValue(SiteSetting::KEY_MAIL_HOST)
            ?? (string) config('mail.mailers.smtp.host', '');
        $port = (int) (SiteSetting::getValue(SiteSetting::KEY_MAIL_PORT)
            ?? config('mail.mailers.smtp.port', 587));
        $username = SiteSetting::getValue(SiteSetting::KEY_MAIL_USERNAME)
            ?? (string) config('mail.mailers.smtp.username', '');

        $encryption = strtolower((string) (SiteSetting::getValue(SiteSetting::KEY_MAIL_ENCRYPTION)
            ?? config('mail.mailers.smtp.encryption')
            ?? 'tls'));
        if (! in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        $fromAddress = SiteSetting::getValue(SiteSetting::KEY_MAIL_FROM_ADDRESS)
            ?? (string) config('mail.from.address', '');
        $fromName = SiteSetting::getValue(SiteSetting::KEY_MAIL_FROM_NAME)
            ?? (string) config('mail.from.name', config('app.name', 'SoundMix Live'));

        $replyTo = SiteSetting::mailReplyTo();

        $password = SiteSetting::mailPassword();
        $hasPassword = filled($password);

        $fromAdmin = filled($storedMailer) || SiteSetting::getValue(SiteSetting::KEY_MAIL_ENABLED) !== null;

        // Effective transport: when mail is disabled or mailer is log, delivery is forced to log.
        $effectiveMailer = ($enabled && $mailer === 'smtp') ? 'smtp' : 'log';
        $ready = $enabled && $mailer === 'smtp' && filled($host) && filled($username) && $hasPassword;

        return [
            'enabled' => $enabled,
            'mailer' => $mailer,
            'effective_mailer' => $effectiveMailer,
            'host' => $host,
            'port' => $port > 0 ? $port : 587,
            'username' => $username,
            'has_password' => $hasPassword,
            'password' => $password,
            'password_masked' => SiteSetting::maskKey($password),
            'encryption' => $encryption,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'reply_to' => $replyTo,
            'from_admin' => $fromAdmin,
            'ready' => $ready,
            'last_test_at' => SiteSetting::getValue(SiteSetting::KEY_MAIL_LAST_TEST_AT),
            'last_test_status' => SiteSetting::getValue(SiteSetting::KEY_MAIL_LAST_TEST_STATUS),
            'last_test_error' => SiteSetting::getValue(SiteSetting::KEY_MAIL_LAST_TEST_ERROR),
            'last_test_recipient' => SiteSetting::getValue(SiteSetting::KEY_MAIL_LAST_TEST_RECIPIENT),
        ];
    }

    /**
     * Apply active settings into runtime configuration and purge cached mailers.
     */
    public function apply(): void
    {
        $settings = $this->getSettings();

        $fromAddress = filled($settings['from_address'])
            ? $settings['from_address']
            : config('mail.from.address');
        $fromName = filled($settings['from_name'])
            ? $settings['from_name']
            : config('mail.from.name', config('app.name', 'SoundMix Live'));

        config([
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        if (filled($settings['reply_to'])) {
            config([
                'mail.reply_to.address' => $settings['reply_to'],
                'mail.reply_to.name' => $fromName,
            ]);
        }

        // Kill switch enforcement: if not enabled or mailer is log, default is log
        if (! $settings['enabled'] || $settings['mailer'] === 'log') {
            config(['mail.default' => 'log']);

            try {
                app('mail.manager')->purge('log');
                app('mail.manager')->purge('smtp');
            } catch (Throwable) {
            }

            return;
        }

        // Live SMTP enabled
        $scheme = $settings['encryption'] === 'ssl' || $settings['port'] === 465 ? 'smtps' : 'smtp';
        $encryption = $settings['encryption'] === 'none' ? null : $settings['encryption'];

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.username' => filled($settings['username']) ? $settings['username'] : null,
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.scheme' => $scheme,
        ]);

        try {
            app('mail.manager')->purge('smtp');
            app('mail.manager')->purge('log');
        } catch (Throwable) {
        }
    }

    /**
     * Send a verification test email with sanitized diagnostics and auditing.
     *
     * @return array{success: bool, message: string, error: ?string}
     */
    public function sendTest(string $recipient, ?User $actor = null): array
    {
        $recipient = trim($recipient);
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => __('Invalid recipient email address.'),
                'error' => __('The provided email address is invalid.'),
            ];
        }

        $this->apply();
        $settings = $this->getSettings();

        // Diagnostics summary without secrets
        $diagnostics = [
            'mailer' => config('mail.default'),
            'host' => $settings['host'],
            'port' => $settings['port'],
            'encryption' => $settings['encryption'],
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'reply_to' => $settings['reply_to'],
        ];

        try {
            Mail::to($recipient)->send(new AdminTestMail($recipient, $diagnostics));

            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_AT, now()->toIso8601String());
            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_STATUS, 'success');
            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_ERROR, null);
            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_RECIPIENT, $recipient);

            AdminMailAudit::record(
                action: AdminMailAudit::ACTION_TEST_SENT,
                status: 'success',
                details: [
                    'recipient' => $recipient,
                    'mailer' => config('mail.default'),
                    'host' => $settings['host'],
                    'port' => $settings['port'],
                ],
                actorId: $actor?->id,
            );

            $transportNote = config('mail.default') === 'log'
                ? __('Written to mail log (safe log mode).')
                : __('Delivered via live SMTP transport.');

            return [
                'success' => true,
                'message' => __('Test email successfully processed for :recipient. :note', [
                    'recipient' => $recipient,
                    'note' => $transportNote,
                ]),
                'error' => null,
            ];
        } catch (Throwable $e) {
            $rawError = $e->getMessage();
            $sanitized = $this->sanitizeError($rawError, $settings['password']);

            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_AT, now()->toIso8601String());
            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_STATUS, 'failure');
            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_ERROR, $sanitized);
            SiteSetting::putValue(SiteSetting::KEY_MAIL_LAST_TEST_RECIPIENT, $recipient);

            AdminMailAudit::record(
                action: AdminMailAudit::ACTION_TEST_FAILED,
                status: 'failure',
                details: [
                    'recipient' => $recipient,
                    'mailer' => config('mail.default'),
                    'error' => $sanitized,
                ],
                actorId: $actor?->id,
            );

            Log::error('Admin SMTP test send failed', [
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'error' => $sanitized,
            ]);

            return [
                'success' => false,
                'message' => __('SMTP delivery failed: :error', ['error' => $sanitized]),
                'error' => $sanitized,
            ];
        }
    }

    /**
     * Thoroughly sanitize an error message to prevent any credential or internal path leakage.
     */
    public function sanitizeError(string $error, ?string $knownSecret = null): string
    {
        $sanitized = EmailDelivery::sanitizeErrorMessage($error);

        if (filled($knownSecret)) {
            $sanitized = str_replace($knownSecret, '[REDACTED]', $sanitized);
        }

        // Strip SMTP authentication payload strings
        $sanitized = preg_replace('/AUTH\s+(LOGIN|PLAIN)\s+[A-Za-z0-9+\/=]+/i', 'AUTH $1 [REDACTED]', $sanitized);
        $sanitized = preg_replace('/(\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b):[^\s]+/i', '$1:[REDACTED]', $sanitized);

        return mb_substr($sanitized, 0, 500);
    }
}
