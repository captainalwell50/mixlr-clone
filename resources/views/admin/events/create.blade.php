@extends('layouts.app')

@section('title', 'Schedule event')

@section('content')
    <h1 class="text-2xl font-semibold text-white">Schedule event</h1>
    <p class="mt-1 text-sm text-zinc-400">One link for promotion that becomes the live page when you go on air.</p>

    <form method="POST" action="{{ route('admin.events.store') }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        <div>
            <label for="organization_id" class="block text-sm text-zinc-300">Channel</label>
            <select id="organization_id" name="organization_id" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                @foreach ($organizations as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="title" class="block text-sm text-zinc-300">Title</label>
            <input id="title" name="title" required value="{{ old('title') }}" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="description" class="block text-sm text-zinc-300">Description</label>
            <textarea id="description" name="description" rows="3" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">{{ old('description') }}</textarea>
        </div>
        <div>
            <label for="scheduled_at" class="block text-sm text-zinc-300">Scheduled start</label>
            <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="access" class="block text-sm text-zinc-300">Access</label>
            <select id="access" name="access" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                <option value="public">Public (Discover)</option>
                <option value="unlisted">Unlisted (link only)</option>
                <option value="private">Private (password)</option>
            </select>
        </div>
        <div>
            <label for="access_password" class="block text-sm text-zinc-300">Password (private only)</label>
            <input id="access_password" type="text" name="access_password" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="checkbox" name="chat_enabled" value="1" checked class="rounded border-zinc-600"> Chat enabled
        </label>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="checkbox" name="show_listener_count" value="1" checked class="rounded border-zinc-600"> Show listener count
        </label>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Schedule</button>
            <a href="{{ route('admin.events.index') }}" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300">Cancel</a>
        </div>
    </form>
@endsection
