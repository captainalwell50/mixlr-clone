@extends('emails.layout')

@section('content')
    <h1>Your Free Trial Ends Soon</h1>

    <p>Hello {{ $notifiable->name ?? 'Creator' }},</p>

    <p>Your Free trial for <strong>{{ $organization->name }}</strong> on SoundMix Live has <strong>{{ $daysLeft }} day(s) remaining</strong>.</p>

    <div class="box" style="border-left: 4px solid #f59e0b;">
        <p style="margin: 0; color: #ffffff; font-weight: 600;">Broadcast Access Cut-off: {{ $endsAtFormatted }}</p>
        <p style="margin: 8px 0 0 0; color: #cbd5e1; font-size: 14px;">
            To ensure your live streaming and audio archive recordings continue without interruption, please choose an active subscription plan before your trial ends.
        </p>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $upgradeUrl }}" class="btn">Upgrade Your Plan Now</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        Questions about our broadcaster plans? Contact our billing team at <a href="mailto:billing@soundmix.live" style="color: #38bdf8;">billing@soundmix.live</a>.
    </p>
@endsection
