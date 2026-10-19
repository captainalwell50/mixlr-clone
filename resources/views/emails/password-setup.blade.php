@extends('emails.layout')

@section('content')
    <h1>{{ $isInitialSetup ? 'Set Up Your Password' : 'Reset Your Password' }}</h1>

    <p>Hello {{ $user->name }},</p>

    <p>
        {{ $isInitialSetup 
            ? 'An account has been prepared for you on SoundMix Live. Please choose a password to complete your account setup.' 
            : 'We received a request to reset your SoundMix Live account password.' 
        }}
    </p>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn">{{ $isInitialSetup ? 'Set Password' : 'Reset Password' }}</a>
    </div>

    <p style="font-size: 14px; color: #94a3b8;">
        This secure link expires in {{ $expiresInMinutes }} minutes and can only be used once. If you did not request this, no action is required and your account remains safe.
    </p>

    <p>If you're having trouble clicking the button, copy and paste this URL into your web browser:<br>
        <span style="color: #38bdf8; font-size: 13px; word-break: break-all;">{{ $actionUrl }}</span>
    </p>
@endsection
