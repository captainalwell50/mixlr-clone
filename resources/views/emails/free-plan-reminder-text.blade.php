@extends('emails.layout-text')

@section('content')
YOUR FREE TRIAL ENDS SOON

Hello {{ $notifiable->name ?? 'Creator' }},

Your Free trial for {{ $organization->name }} on SoundMix Live has {{ $daysLeft }} day(s) remaining.

Broadcast Access Cut-off: {{ $endsAtFormatted }}

To ensure your live streaming and audio archive recordings continue without interruption, please choose an active subscription plan before your trial ends.

Upgrade Your Plan Now:
{{ $upgradeUrl }}

Questions about our broadcaster plans? Contact our billing team at billing@soundmix.live.
@endsection
