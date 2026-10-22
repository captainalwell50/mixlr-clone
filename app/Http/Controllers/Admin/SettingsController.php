<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminMailAudit;
use App\Models\SiteSetting;
use App\Models\Stream;
use App\Services\AzureVmCapacityService;
use App\Services\MailConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request, AzureVmCapacityService $vmCapacity): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $status = Stream::listenPlaybackStatus();

        $paystack = app(\App\Services\PaystackClient::class);
        $secret = SiteSetting::paystackSecretKey();

        $mail = SiteSetting::mailSettings();

        return view('admin.settings.edit', [
            'listenStatus' => $status,
            'listenPreferHlsFormValue' => old('listen_prefer_hls', $status['form_value']),
            'opusSendKbps' => (int) old('opus_send_kbps', SiteSetting::opusSendKbps()),
            'opusSendOptions' => SiteSetting::OPUS_SEND_KBPS_OPTIONS,
            'vmCapacity' => $vmCapacity->snapshot(),
            'paystackEnabled' => $paystack->enabled(),
            'paystackPublicKey' => old('paystack_public_key', SiteSetting::paystackPublicKey() ?? ''),
            'paystackSecretMask' => SiteSetting::maskKey($secret),
            'paystackSecretFromAdmin' => SiteSetting::paystackSecretIsFromAdmin(),
            'paystackCurrency' => old('paystack_currency', SiteSetting::paystackCurrency()),
            'paystackWebhookUrl' => url('/webhooks/paystack'),
            'supportEmail' => old('support_email', SiteSetting::supportEmail()),
            'supportEmailFromAdmin' => SiteSetting::supportEmailIsFromAdmin(),
            'whatsappEnabled' => (bool) old('whatsapp_support_enabled', SiteSetting::whatsappSupportEnabled()),
            'whatsappNumber' => old('whatsapp_support_number', SiteSetting::whatsappSupportNumberRaw()),
            'whatsappNumberFromAdmin' => SiteSetting::whatsappSupportIsFromAdmin(),
            'whatsappDefaultMessage' => old('whatsapp_default_message', SiteSetting::whatsappDefaultMessageRaw()),
            'mailEnabled' => (bool) old('mail_enabled', $mail['enabled']),
            'mailMailer' => old('mail_mailer', $mail['mailer']),
            'mailEffectiveMailer' => $mail['effective_mailer'],
            'mailHost' => old('mail_host', $mail['host']),
            'mailPort' => old('mail_port', $mail['port']),
            'mailUsername' => old('mail_username', $mail['username']),
            'mailPasswordMask' => $mail['password_masked'],
            'mailPasswordFromAdmin' => SiteSetting::mailPasswordIsFromAdmin(),
            'mailEncryption' => old('mail_encryption', $mail['encryption']),
            'mailFromAddress' => old('mail_from_address', $mail['from_address']),
            'mailFromName' => old('mail_from_name', $mail['from_name']),
            'mailReplyTo' => old('mail_reply_to', $mail['reply_to']),
            'mailFromAdmin' => $mail['from_admin'],
            'mailReady' => $mail['ready'],
            'mailLastTestAt' => $mail['last_test_at'],
            'mailLastTestStatus' => $mail['last_test_status'],
            'mailLastTestError' => $mail['last_test_error'],
            'mailLastTestRecipient' => $mail['last_test_recipient'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'listen_prefer_hls' => ['required', 'string', Rule::in(SiteSetting::LISTEN_PREFER_HLS_OPTIONS)],
            'opus_send_kbps' => ['sometimes', 'integer', Rule::in(SiteSetting::OPUS_SEND_KBPS_OPTIONS)],
            'vm_hold_peak_hours' => ['nullable', 'integer', 'min:1', 'max:72'],
            'vm_clear_peak_hold' => ['sometimes', 'boolean'],
        ]);

        SiteSetting::putValue(
            SiteSetting::KEY_LISTEN_PREFER_HLS,
            $validated['listen_prefer_hls'],
        );

        if (isset($validated['opus_send_kbps'])) {
            SiteSetting::putValue(
                SiteSetting::KEY_OPUS_SEND_KBPS,
                (string) $validated['opus_send_kbps'],
            );
        }

        if ($request->boolean('vm_clear_peak_hold')) {
            app(AzureVmCapacityService::class)->holdPeakForHours(0);
        } elseif (! empty($validated['vm_hold_peak_hours'])) {
            app(AzureVmCapacityService::class)->holdPeakForHours((int) $validated['vm_hold_peak_hours']);
        }

        return redirect()->route('admin.settings.edit')
            ->with('status', __('Platform settings updated.'));
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'paystack_public_key' => ['nullable', 'string', 'max:255'],
            'paystack_secret_key' => ['nullable', 'string', 'max:255'],
            'paystack_currency' => ['nullable', 'string', 'size:3'],
            'paystack_clear_secret' => ['sometimes', 'boolean'],
        ]);

        $public = trim((string) ($validated['paystack_public_key'] ?? ''));
        SiteSetting::putValue(
            SiteSetting::KEY_PAYSTACK_PUBLIC,
            $public !== '' ? $public : null,
        );

        if ($request->boolean('paystack_clear_secret')) {
            SiteSetting::putPaystackSecret(null);
        } elseif (filled($validated['paystack_secret_key'] ?? null)) {
            SiteSetting::putPaystackSecret((string) $validated['paystack_secret_key']);
        }

        $currency = strtoupper(trim((string) ($validated['paystack_currency'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $currency)) {
            SiteSetting::putValue(SiteSetting::KEY_PAYSTACK_CURRENCY, $currency);
        }

        return redirect()->route('admin.settings.edit')
            ->with('status', __('Paystack settings saved.'));
    }

    public function updateContact(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'support_email' => ['nullable', 'email', 'max:255'],
            'whatsapp_support_enabled' => ['nullable', 'boolean'],
            'whatsapp_support_number' => ['nullable', 'string', 'max:40'],
            'whatsapp_default_message' => ['nullable', 'string', 'max:500'],
        ]);

        $rawNumber = trim((string) ($request->input('whatsapp_support_number') ?? ''));
        if ($rawNumber !== '') {
            $normalized = SiteSetting::normalizeWhatsAppNumber($rawNumber);
            if ($normalized === null) {
                return back()
                    ->withErrors(['whatsapp_support_number' => __('Use an international WhatsApp number, e.g. 2348012345678.')])
                    ->withInput();
            }
        } else {
            $normalized = null;
        }

        $email = trim((string) ($validated['support_email'] ?? ''));
        SiteSetting::putSupportEmail($email !== '' ? $email : null);
        config(['app.support_email' => SiteSetting::supportEmail()]);

        $enabled = $request->boolean('whatsapp_support_enabled');
        $defaultMsg = trim((string) ($validated['whatsapp_default_message'] ?? ''));

        SiteSetting::putWhatsAppSupport($normalized, $enabled, $defaultMsg !== '' ? $defaultMsg : null);

        return redirect()->route('admin.settings.edit')
            ->with('status', __('Contact and WhatsApp support settings saved.'));
    }

    public function updateMail(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'mail_enabled' => ['sometimes', 'boolean'],
            'mail_mailer' => ['required', 'in:smtp,log'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_reply_to' => ['nullable', 'email', 'max:255'],
            'mail_clear_password' => ['sometimes', 'boolean'],
        ]);

        $wasEnabled = SiteSetting::mailEnabled();
        $newEnabled = $request->has('mail_enabled')
            ? $request->boolean('mail_enabled')
            : ($validated['mail_mailer'] === 'smtp');
        SiteSetting::putMailEnabled($newEnabled);

        SiteSetting::putValue(SiteSetting::KEY_MAIL_MAILER, $validated['mail_mailer']);
        SiteSetting::putValue(SiteSetting::KEY_MAIL_HOST, trim((string) ($validated['mail_host'] ?? '')) ?: null);
        SiteSetting::putValue(
            SiteSetting::KEY_MAIL_PORT,
            isset($validated['mail_port']) ? (string) $validated['mail_port'] : null,
        );
        SiteSetting::putValue(SiteSetting::KEY_MAIL_USERNAME, trim((string) ($validated['mail_username'] ?? '')) ?: null);
        SiteSetting::putValue(SiteSetting::KEY_MAIL_ENCRYPTION, $validated['mail_encryption']);
        SiteSetting::putValue(SiteSetting::KEY_MAIL_FROM_ADDRESS, trim((string) ($validated['mail_from_address'] ?? '')) ?: null);
        SiteSetting::putValue(SiteSetting::KEY_MAIL_FROM_NAME, trim((string) ($validated['mail_from_name'] ?? '')) ?: null);
        SiteSetting::putMailReplyTo(trim((string) ($validated['mail_reply_to'] ?? '')) ?: null);

        $passwordAction = 'preserved';
        if ($request->boolean('mail_clear_password')) {
            SiteSetting::putMailPassword(null);
            $passwordAction = 'cleared';
        } elseif (filled($validated['mail_password'] ?? null)) {
            SiteSetting::putMailPassword((string) $validated['mail_password']);
            $passwordAction = 'updated';
        }

        SiteSetting::applyMailConfig();

        // Audit logging without secrets
        AdminMailAudit::record(
            action: AdminMailAudit::ACTION_SETTINGS_UPDATED,
            status: 'success',
            details: [
                'enabled' => $newEnabled,
                'mailer' => $validated['mail_mailer'],
                'host' => trim((string) ($validated['mail_host'] ?? '')),
                'port' => $validated['mail_port'] ?? null,
                'encryption' => $validated['mail_encryption'],
                'from_address' => trim((string) ($validated['mail_from_address'] ?? '')),
                'reply_to' => trim((string) ($validated['mail_reply_to'] ?? '')),
                'password_action' => $passwordAction,
            ],
            actorId: $request->user()?->id,
        );

        if ($wasEnabled !== $newEnabled) {
            AdminMailAudit::record(
                action: $newEnabled
                    ? AdminMailAudit::ACTION_DELIVERY_ENABLED
                    : AdminMailAudit::ACTION_DELIVERY_DISABLED,
                status: 'success',
                details: [
                    'mailer' => $validated['mail_mailer'],
                ],
                actorId: $request->user()?->id,
            );
        }

        $feedback = $newEnabled
            ? __('Email settings saved. Live outbound delivery is ENABLED.')
            : __('Email settings saved. Outbound delivery is in SAFE/LOG mode.');

        return redirect()->route('admin.settings.edit')
            ->with('status', $feedback);
    }

    public function sendTestMail(Request $request, MailConfigurationService $mailConfig): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->validate([
            'test_recipient' => ['nullable', 'email', 'max:255'],
        ]);

        $recipient = trim((string) $request->input('test_recipient'));
        if ($recipient === '') {
            $recipient = (string) $request->user()?->email;
        }

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('admin.settings.edit')
                ->with('error', __('Please provide a valid recipient email address for testing.'));
        }

        $result = $mailConfig->sendTest($recipient, $request->user());

        if (! $result['success']) {
            return redirect()->route('admin.settings.edit')
                ->with('error', $result['message']);
        }

        return redirect()->route('admin.settings.edit')
            ->with('status', $result['message']);
    }
}
