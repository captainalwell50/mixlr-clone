@extends('emails.layout-text')

@section('content')
Welcome to SoundMix Live!

Hello {{ $user->name }},

Your SoundMix Live account is active and ready. SoundMix gives you ultra-reliable, low-latency live audio broadcasting built specifically for live services and events.

@if(!empty($organizationName))
Organisation: {{ $organizationName }}
@endif
@if(!empty($channelHandle))
Channel Handle: {{ $channelHandle }}
@endif

Get started here:
{{ $ctaUrl }}

Need assistance? Contact our team at {{ $supportEmail }}.

Warm regards,
The SoundMix Live Team
@endsection
