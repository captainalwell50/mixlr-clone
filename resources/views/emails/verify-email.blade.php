@extends('emails.layout')

@section('content')
    <h1>Verify Your Email Address</h1>

    <p>Hello {{ $user->name }},</p>

    <p>Please click the button below to verify your email address for your SoundMix Live account.</p>

    <div class="btn-wrapper">
        <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
    </div>

    <p style="font-size: 14px; color: #94a3b8;">
        This verification link will expire in 60 minutes. If you did not create an account, no further action is required.
    </p>

    <p>If you're having trouble clicking the button, copy and paste this URL into your browser:<br>
        <span style="color: #38bdf8; font-size: 13px; word-break: break-all;">{{ $verificationUrl }}</span>
    </p>
@endsection
