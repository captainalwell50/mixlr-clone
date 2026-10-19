@extends('emails.layout-text')

@section('content')
Verify Your Email Address

Hello {{ $user->name }},

Please click the link below to verify your email address for your SoundMix Live account:
{{ $verificationUrl }}

This verification link expires in 60 minutes. If you did not create an account, no further action is required.
@endsection
