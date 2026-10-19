@extends('emails.layout-text')

@section('content')
PASSWORD CHANGED

Hello {{ $user->name ?? 'Creator' }},

This is a confirmation that the password for your SoundMix Live account ({{ $user->email }}) was successfully updated on {{ $changedAtFormatted }}.
@if(!empty($ipAddress))

Request Origin IP: {{ $ipAddress }}
@endif

DIDN'T MAKE THIS CHANGE?
If you did not change your password, someone else may have gained unauthorized access to your account. Please reset your password immediately and contact our support team at {{ $replyTo ?? 'support@soundmix.live' }}.

Review Account Security:
{{ $accountUrl }}

For your security, SoundMix Live support representatives will never ask you for your password or credentials.
@endsection
