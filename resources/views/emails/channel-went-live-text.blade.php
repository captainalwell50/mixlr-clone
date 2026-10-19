@extends('emails.layout-text')

@section('content')
{{ strtoupper($channel->name) }} IS NOW LIVE!

Hello {{ $notifiable->name ?? 'Listener' }},

{{ $channel->name }} just started a live audio broadcast:
{{ $event->title }}

Listen Live Now:
{{ $listenUrl }}

You received this notification because you follow {{ $channel->name }} on SoundMix Live.
@endsection
