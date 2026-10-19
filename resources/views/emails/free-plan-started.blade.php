@extends('emails.layout')

@section('content')
    <h1>Welcome to Your Free Trial</h1>

    <p>Hello {{ $notifiable->name ?? 'Creator' }},</p>

    <p>Your <strong>{{ $days }}-day Free trial</strong> on SoundMix Live for <strong>{{ $organization->name }}</strong> is now active!</p>

    <div class="box">
        <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 14px; color: #cbd5e1;">
            <tr>
                <td style="color: #94a3b8; width: 40%;">Broadcasting Organisation:</td>
                <td style="font-weight: 600; color: #ffffff;">{{ $organization->name }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Trial Duration:</td>
                <td style="color: #38bdf8; font-weight: 600;">{{ $days }} Days</td>
            </tr>
            @if(!empty($endsAtFormatted))
                <tr>
                    <td style="color: #94a3b8;">Full Access Until:</td>
                    <td style="color: #e2e8f0;">{{ $endsAtFormatted }}</td>
                </tr>
            @endif
            <tr>
                <td style="color: #94a3b8;">Broadcast Quality:</td>
                <td style="color: #34d399; font-weight: 600;">Studio Opus Audio Streaming</td>
            </tr>
        </table>
    </div>

    <p style="font-size: 14px; color: #cbd5e1;">
        You can go live immediately using the SoundMix Web Studio or Mobile Broadcaster. You may upgrade your plan at any time to ensure continuous streaming.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $upgradeUrl }}" class="btn">Explore Subscription Plans</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        Need setup assistance? Reach our team at <a href="mailto:{{ $supportEmail }}" style="color: #38bdf8;">{{ $supportEmail }}</a>.
    </p>
@endsection
