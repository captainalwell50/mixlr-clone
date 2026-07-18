@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-semibold text-white">Dashboard</h1>
    <p class="mt-2 text-zinc-400">Creator tools and listening shortcuts.</p>
    <div class="mt-6 flex flex-wrap gap-3">
        @if(auth()->user()->is_admin || auth()->user()->manageableOrganizations()->exists())
            <a href="{{ route('admin.events.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Schedule event</a>
            <a href="{{ route('admin.events.index') }}" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">My events</a>
            <a href="{{ route('admin.analytics.index') }}" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">Analytics</a>
        @endif
        <a href="{{ route('discover') }}" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">Discover</a>
        <a href="{{ route('archive.index') }}" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">Archive</a>
    </div>
    <p class="mt-8 text-xs text-zinc-600">Install this site as an app from your browser for a standalone creator / listener experience.</p>
@endsection
