@extends('emails.layout')

@section('content')
    <h1>Password Changed</h1>
    <p style="font-size: 14px; color: #94a3b8; margin-top: -8px; margin-bottom: 20px;">Security Notice</p>

    <p>Hello {{ $user->name ?? 'Creator' }},</p>

    <p>This is a confirmation that the password for your SoundMix Live account (<strong>{{ $user->email }}</strong>) was successfully updated on <strong>{{ $changedAtFormatted }}</strong>.</p>

    @if(!empty($ipAddress))
        <div class="box">
            <span style="color: #94a3b8; font-size: 13px;">Request Details:</span><br>
            <strong style="color: #e2e8f0; font-size: 14px;">Origin IP:</strong> <span style="font-family: monospace; color: #38bdf8;">{{ $ipAddress }}</span>
        </div>
    @endif

    <div class="box" style="border-left: 4px solid #38bdf8;">
        <strong style="color: #ffffff;">Didn't make this change?</strong>
        <p style="margin-top: 8px; margin-bottom: 0; color: #cbd5e1; font-size: 14px;">
            If you did not change your password, someone else may have gained unauthorized access to your account.
            Please reset your password immediately and contact our support team at
            <a href="mailto:{{ $replyTo ?? 'support@soundmix.live' }}" style="color: #38bdf8;">{{ $replyTo ?? 'support@soundmix.live' }}</a>.
        </p>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $accountUrl }}" class="btn">Review Account Security</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        For your security, SoundMix Live support representatives will never ask you for your password or credentials.
    </p>
@endsection
