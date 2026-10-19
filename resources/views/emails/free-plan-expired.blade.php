@extends('emails.layout')

@section('content')
    <h1>Your Free Trial Has Ended</h1>

    <p>Hello {{ $notifiable->name ?? 'Creator' }},</p>

    <p>The Free trial period for <strong>{{ $organization->name }}</strong> on SoundMix Live has concluded.</p>

    <div class="box" style="border-left: 4px solid #ef4444;">
        <p style="margin: 0; color: #ffffff; font-weight: 600;">Status: Studio Broadcasting Paused</p>
        <p style="margin: 8px 0 0 0; color: #cbd5e1; font-size: 14px;">
            Your account, profile, channel history, and saved recordings remain completely intact. However, starting a new live broadcast requires an active subscription.
        </p>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $upgradeUrl }}" class="btn">Restore Live Broadcasting Access</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        Need assistance or a tailored plan for your organisation? We're here to help at <a href="mailto:support@soundmix.live" style="color: #38bdf8;">support@soundmix.live</a>.
    </p>
@endsection
