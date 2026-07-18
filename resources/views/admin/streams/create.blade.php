@extends('layouts.app')

@section('title', 'New stream')

@section('content')
    <h1 class="text-2xl font-semibold text-white">New stream</h1>
    <p class="mt-1 text-sm text-zinc-400">Creates a MediaMTX path for browser WHIP, OBS/RTMP, and HLS.</p>

    <form method="POST" action="{{ route('admin.streams.store') }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        <div>
            <label for="organization_id" class="block text-sm font-medium text-zinc-300">Organization</label>
            <select id="organization_id" name="organization_id" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                @foreach ($organizations as $org)
                    <option value="{{ $org->id }}" @selected(old('organization_id') == $org->id)>{{ $org->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="title" class="block text-sm font-medium text-zinc-300">Title</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required placeholder="Sunday service"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-zinc-300">Description</label>
            <textarea id="description" name="description" rows="2"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none">{{ old('description') }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', true)) class="rounded border-zinc-600">
            List on Discover
        </label>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="checkbox" name="chat_enabled" value="1" @checked(old('chat_enabled', true)) class="rounded border-zinc-600">
            Enable live chat
        </label>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create</button>
            <a href="{{ route('admin.streams.index') }}" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">Cancel</a>
        </div>
    </form>
@endsection
