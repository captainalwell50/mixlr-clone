@extends('emails.layout')

@section('content')
    <h1>Welcome to SoundMix Live!</h1>

    <p>Hello {{ $user->name }},</p>

    <p>Your SoundMix Live account is active and ready. SoundMix gives you ultra-reliable, low-latency live audio broadcasting built specifically for live services and events.</p>

    @if(!empty($channelHandle) || !empty($organizationName))
        <div class="box">
            @if(!empty($organizationName))
                <p style="margin: 0 0 6px 0;"><strong>Organisation:</strong> {{ $organizationName }}</p>
            @endif
            @if(!empty($channelHandle))
                <p style="margin: 0;"><strong>Channel Handle:</strong> {{ $channelHandle }}</p>
            @endif
        </div>
    @endif

    <div class="btn-wrapper">
        <a href="{{ $ctaUrl }}" class="btn">Set Up Your Channel</a>
    </div>

    <p>Need assistance getting started? Our team is available at <a href="mailto:{{ $supportEmail }}" style="color: #38bdf8;">{{ $supportEmail }}</a>.</p>

    <p>Warm regards,<br>The SoundMix Live Team</p>
@endsection
