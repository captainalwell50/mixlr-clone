SOUNDMIX LIVE — SMTP OUTBOUND DELIVERY VERIFIED
==================================================

Hello Administrator,

This test email confirms that SoundMix Live outbound mail delivery is functioning properly with the active platform configuration.

DISPATCH DIAGNOSTICS:
- Dispatched At: {{ $dispatchedAtFormatted }}
- Recipient: {{ $recipientAddress }}
@if(!empty($settingsSummary['mailer']))
- Transport: {{ strtoupper($settingsSummary['mailer']) }}
@endif
@if(!empty($settingsSummary['host']))
- SMTP Host: {{ $settingsSummary['host'] }}:{{ $settingsSummary['port'] ?? 587 }}
@endif
@if(!empty($settingsSummary['encryption']))
- Encryption: {{ strtoupper($settingsSummary['encryption']) }}
@endif
@if(!empty($settingsSummary['from_address']))
- From Address: {{ $settingsSummary['from_address'] }}
@endif

Transactional events (password resets, account verification, receipts, billing notifications) will be dispatched through this active channel.

Manage Settings:
{{ $siteUrl }}/admin/settings#mail

Sent from SoundMix Super Admin Console.
