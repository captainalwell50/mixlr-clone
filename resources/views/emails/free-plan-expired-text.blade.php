@extends('emails.layout-text')

@section('content')
YOUR FREE TRIAL HAS ENDED

Hello {{ $notifiable->name ?? 'Creator' }},

The Free trial period for {{ $organization->name }} on SoundMix Live has concluded.

STATUS: Studio Broadcasting Paused
Your account, profile, channel history, and saved recordings remain completely intact. However, starting a new live broadcast requires an active subscription.

Restore Live Broadcasting Access:
{{ $upgradeUrl }}

Need assistance or a tailored plan for your organisation? We're here to help at support@soundmix.live.
@endsection
