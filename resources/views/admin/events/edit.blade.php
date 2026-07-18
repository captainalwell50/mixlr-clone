@extends('layouts.app')

@section('title', 'Edit event')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="console-title">{{ $event->title }}</h1>
            <p class="console-lead">{{ $event->organization->name }} · {{ $event->status->value }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($event->status->value !== 'live')
                <form method="POST" action="{{ route('admin.events.go-live', $event) }}">
                    @csrf
                    <button type="submit" class="console-btn console-btn-primary">Go live</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.events.end', $event) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-zinc-700 px-4 py-2 text-sm font-semibold text-white">End event</button>
                </form>
                <a href="{{ route('admin.streams.studio', $stream) }}" target="_blank" class="console-btn console-btn-ghost">Open studio</a>
            @endif
        </div>
    </div>

    <div class="mt-6 space-y-3 rounded-xl border border-emerald-900/40 bg-emerald-950/20 p-5 text-sm">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-emerald-400">Share this link</h2>
        <div class="flex flex-col gap-2 sm:flex-row">
            <code id="event-url" class="flex-1 break-all rounded bg-zinc-950 px-2 py-1.5 text-xs text-zinc-300">{{ route('events.show', $event) }}</code>
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('event-url').textContent)"
                class="rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300">Copy</button>
        </div>
        <p class="text-xs text-zinc-500">Studio (24h): <code class="text-amber-200/90">{{ $studioUrl }}</code></p>
        <p class="text-xs text-zinc-500">OBS server: <code class="text-zinc-300">{{ $stream->rtmpUrl() }}</code></p>
        <p class="text-xs text-zinc-500">OBS key: <code class="text-amber-200/90">{{ $stream->rtmpStreamKeyForObs() }}</code></p>
    </div>

    <form method="POST" action="{{ route('admin.events.update', $event) }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="organization_id" class="block text-sm text-zinc-300">Channel</label>
            <select id="organization_id" name="organization_id" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                @foreach ($organizations as $org)
                    <option value="{{ $org->id }}" @selected($event->organization_id == $org->id)>{{ $org->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="title" class="block text-sm text-zinc-300">Title</label>
            <input id="title" name="title" required value="{{ old('title', $event->title) }}" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="description" class="block text-sm text-zinc-300">Description</label>
            <textarea id="description" name="description" rows="3" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">{{ old('description', $event->description) }}</textarea>
        </div>
        <div>
            <label for="scheduled_at" class="block text-sm text-zinc-300">Scheduled start</label>
            <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                value="{{ old('scheduled_at', $event->scheduled_at?->format('Y-m-d\TH:i')) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="access" class="block text-sm text-zinc-300">Access</label>
            <select id="access" name="access" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                @foreach (['public','unlisted','private'] as $a)
                    <option value="{{ $a }}" @selected(old('access', $event->access->value) === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="access_password" class="block text-sm text-zinc-300">New password (private; leave blank to keep)</label>
            <input id="access_password" type="text" name="access_password" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="chat_enabled" value="0">
            <input type="checkbox" name="chat_enabled" value="1" @checked(old('chat_enabled', $event->chat_enabled)) class="rounded border-zinc-600"> Chat enabled
        </label>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="show_listener_count" value="0">
            <input type="checkbox" name="show_listener_count" value="1" @checked(old('show_listener_count', $event->show_listener_count)) class="rounded border-zinc-600"> Show listener count
        </label>
        <div class="flex gap-3">
            <button type="submit" class="console-btn console-btn-primary">Save</button>
            <a href="{{ route('admin.events.index') }}" class="console-btn console-btn-ghost">Back</a>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="mt-8" onsubmit="return confirm('Delete this event?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-red-300 hover:text-red-200">Delete event</button>
    </form>
@endsection
