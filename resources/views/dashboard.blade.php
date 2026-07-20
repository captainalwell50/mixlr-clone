@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <p class="site-section-label">Operator</p>
    <h1 class="console-title mt-2">Dashboard</h1>
    <p class="console-lead">Creator tools and listening shortcuts for Live Mix Audio.</p>

    <div class="console-actions mt-8">
        @if(auth()->user()->is_admin || auth()->user()->manageableOrganizations()->exists())
            <a href="{{ route('admin.events.create') }}" class="console-btn console-btn-primary">Schedule event</a>
            <a href="{{ route('admin.events.index') }}" class="console-btn console-btn-ghost">My events</a>
            <a href="{{ route('admin.analytics.index') }}" class="console-btn console-btn-ghost">Analytics</a>
            <a href="{{ route('admin.streams.index') }}" class="console-btn console-btn-ghost">Streams</a>
        @endif
        <a href="{{ route('discover') }}" class="console-btn console-btn-ghost">Discover</a>
        <a href="{{ route('archive.index') }}" class="console-btn console-btn-ghost">Recorded Audio</a>
    </div>

    <p class="mt-10 text-xs text-[var(--stage-muted)]">
        Tip: install Live Mix Audio from your browser for a standalone creator / listener experience.
    </p>
@endsection
