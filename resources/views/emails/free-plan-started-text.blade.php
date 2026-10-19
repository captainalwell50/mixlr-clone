@extends('emails.layout-text')

@section('content')
WELCOME TO YOUR FREE TRIAL

Hello {{ $notifiable->name ?? 'Creator' }},

Your {{ $days }}-day Free trial on SoundMix Live for {{ $organization->name }} is now active!

TRIAL DETAILS:
- Organisation: {{ $organization->name }}
- Duration: {{ $days }} Days
@if(!empty($endsAtFormatted))
- Full Access Until: {{ $endsAtFormatted }}
@endif
- Broadcast Quality: Studio Opus Audio Streaming

You can go live immediately using the SoundMix Web Studio or Mobile Broadcaster. You may upgrade your plan at any time to ensure continuous streaming.

Explore Subscription Plans:
{{ $upgradeUrl }}

Need setup assistance? Reach our team at {{ $supportEmail }}.
@endsection
