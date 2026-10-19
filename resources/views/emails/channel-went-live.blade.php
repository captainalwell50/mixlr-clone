@extends('emails.layout')

@section('content')
    <h1>{{ $channel->name }} is Now Live!</h1>

    <p>Hello {{ $notifiable->name ?? 'Listener' }},</p>

    <p><strong>{{ $channel->name }}</strong> just started a live audio broadcast:</p>

    <div class="box" style="border-left: 4px solid #38bdf8;">
        <h2 style="font-size: 18px; color: #ffffff; margin: 0 0 8px 0;">{{ $event->title }}</h2>
        <p style="margin: 0; color: #94a3b8; font-size: 13px;">Live Audio Broadcast &bull; High-Fidelity Opus Stream</p>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $listenUrl }}" class="btn">Listen Live Now</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        You received this notification because you follow {{ $channel->name }} on SoundMix Live.
    </p>
@endsection
