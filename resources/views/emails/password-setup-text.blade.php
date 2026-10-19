@extends('emails.layout-text')

@section('content')
{{ $isInitialSetup ? 'Set Up Your Password' : 'Reset Your Password' }}

Hello {{ $user->name }},

{{ $isInitialSetup 
    ? 'An account has been prepared for you on SoundMix Live. Please choose a password to complete your account setup.' 
    : 'We received a request to reset your SoundMix Live account password.' 
}}

Use the secure link below to proceed:
{{ $actionUrl }}

This link expires in {{ $expiresInMinutes }} minutes and can only be used once. If you did not request this, you can safely ignore this message.
@endsection
