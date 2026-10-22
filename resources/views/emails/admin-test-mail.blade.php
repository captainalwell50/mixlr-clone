@extends('emails.layout')

@section('content')
    <h1>SMTP Outbound Delivery Verified</h1>
    <p style="font-size: 14px; color: #94a3b8; margin-top: -8px; margin-bottom: 20px;">Platform Delivery Verification</p>

    <p>Hello Administrator,</p>

    <p>This test email confirms that SoundMix Live outbound mail delivery is functioning properly with the active platform configuration.</p>

    <div class="box" style="border-left: 4px solid #10b981; background-color: #0f172a; padding: 16px; border-radius: 6px; margin: 20px 0;">
        <strong style="color: #10b981; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em;">Dispatch Diagnostics</strong>
        <table style="width: 100%; margin-top: 12px; font-size: 13px; color: #cbd5e1; border-collapse: collapse;">
            <tr>
                <td style="padding: 4px 0; color: #94a3b8; width: 140px;">Dispatched At:</td>
                <td style="padding: 4px 0; font-family: monospace; color: #e2e8f0;">{{ $dispatchedAtFormatted }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #94a3b8;">Recipient:</td>
                <td style="padding: 4px 0; font-family: monospace; color: #38bdf8;">{{ $recipientAddress }}</td>
            </tr>
            @if(!empty($settingsSummary['mailer']))
            <tr>
                <td style="padding: 4px 0; color: #94a3b8;">Transport:</td>
                <td style="padding: 4px 0; font-family: monospace; color: #e2e8f0;">{{ strtoupper($settingsSummary['mailer']) }}</td>
            </tr>
            @endif
            @if(!empty($settingsSummary['host']))
            <tr>
                <td style="padding: 4px 0; color: #94a3b8;">SMTP Host:</td>
                <td style="padding: 4px 0; font-family: monospace; color: #e2e8f0;">{{ $settingsSummary['host'] }}:{{ $settingsSummary['port'] ?? 587 }}</td>
            </tr>
            @endif
            @if(!empty($settingsSummary['encryption']))
            <tr>
                <td style="padding: 4px 0; color: #94a3b8;">Encryption:</td>
                <td style="padding: 4px 0; font-family: monospace; color: #e2e8f0;">{{ strtoupper($settingsSummary['encryption']) }}</td>
            </tr>
            @endif
            @if(!empty($settingsSummary['from_address']))
            <tr>
                <td style="padding: 4px 0; color: #94a3b8;">From Address:</td>
                <td style="padding: 4px 0; font-family: monospace; color: #e2e8f0;">{{ $settingsSummary['from_address'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    <p style="font-size: 14px; color: #cbd5e1;">
        Transactional events (password resets, account verification, receipts, billing notifications) will be dispatched through this active channel.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $siteUrl }}/admin/settings#mail" class="btn">Open Email Settings</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        This test message was initiated from the SoundMix Super Admin Console. If you did not trigger this test, please inspect the admin audit ledger.
    </p>
@endsection
