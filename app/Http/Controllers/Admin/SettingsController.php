<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Stream;
use App\Services\AzureVmCapacityService;
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
            'mailMailer' => old('mail_mailer', $mail['mailer']),
            'mailHost' => old('mail_host', $mail['host']),
            'mailPort' => old('mail_port', $mail['port']),
            'mailUsername' => old('mail_username', $mail['username']),
            'mailPasswordMask' => SiteSetting::maskKey($mail['password']),
            'mailPasswordFromAdmin' => SiteSetting::mailPasswordIsFromAdmin(),
            'mailEncryption' => old('mail_encryption', $mail['encryption']),
            'mailFromAddress' => old('mail_from_address', $mail['from_address']),
            'mailFromName' => old('mail_from_name', $mail['from_name']),
            'mailFromAdmin' => $mail['from_admin'],
            'mailReady' => $mail['ready'],
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
            'mail_mailer' => ['required', 'in:smtp,log'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_clear_password' => ['sometimes', 'boolean'],
        ]);

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

        if ($request->boolean('mail_clear_password')) {
            SiteSetting::putMailPassword(null);
        } elseif (filled($validated['mail_password'] ?? null)) {
            SiteSetting::putMailPassword((string) $validated['mail_password']);
        }

        SiteSetting::applyMailConfig();

        return redirect()->route('admin.settings.edit')
            ->with('status', __('Email settings saved.'));
    }

    public function sendTestMail(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        SiteSetting::applyMailConfig();
        $to = $request->user()?->email;
        if (! is_string($to) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('admin.settings.edit')
                ->with('error', __('Your admin account has no valid email address.'));
        }

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'Sound Mix Live can send mail with the SMTP settings saved in Platform settings.',
                function ($message) use ($to): void {
                    $message->to($to)->subject('Sound Mix Live test email');
                },
            );
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.edit')
                ->with('error', __('Could not send test email: :error', ['error' => $e->getMessage()]));
        }

        $mailer = config('mail.default');

        return redirect()->route('admin.settings.edit')
            ->with('status', $mailer === 'log'
                ? __('Test email written to the mail log (MAIL_MAILER=log). Switch to SMTP to send through Gmail.')
                : __('Test email sent to :email.', ['email' => $to]));
    }
}
