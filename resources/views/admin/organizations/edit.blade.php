@extends('layouts.app')

@section('title', 'Edit channel')

@section('content')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="console-title">Channel branding</h1>
        <a href="{{ route('channels.show', $organization) }}" target="_blank" class="text-sm text-emerald-400 hover:text-emerald-300">View channel →</a>
    </div>

    <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="block text-sm font-medium text-zinc-300">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $organization->name) }}" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-zinc-300">Slug (URL)</label>
            <input id="slug" type="text" name="slug" value="{{ old('slug', $organization->slug) }}" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            <p class="mt-1 text-xs text-zinc-500">{{ url('/c/'.$organization->slug) }}</p>
        </div>
        <div>
            <label for="tagline" class="block text-sm font-medium text-zinc-300">Tagline</label>
            <input id="tagline" type="text" name="tagline" value="{{ old('tagline', $organization->tagline) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="theme_color" class="block text-sm font-medium text-zinc-300">Theme color</label>
            <input id="theme_color" type="text" name="theme_color" value="{{ old('theme_color', $organization->theme_color ?: '#3d9b7a') }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white" placeholder="#3d9b7a">
        </div>
        <div>
            <label for="support_url" class="block text-sm font-medium text-zinc-300">Support / donate URL</label>
            <input id="support_url" type="url" name="support_url" value="{{ old('support_url', $organization->support_url) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white" placeholder="https://…">
        </div>
        <div>
            <label for="logo_path" class="block text-sm font-medium text-zinc-300">Logo URL</label>
            <input id="logo_path" type="text" name="logo_path" value="{{ old('logo_path', $organization->logo_path) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="artwork_path" class="block text-sm font-medium text-zinc-300">Default artwork URL</label>
            <input id="artwork_path" type="text" name="artwork_path" value="{{ old('artwork_path', $organization->artwork_path) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="is_public" value="0">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $organization->is_public ?? true)) class="rounded border-zinc-600">
            Public channel (discoverable)
        </label>
        <div class="flex gap-3">
            <button type="submit" class="console-btn console-btn-primary">Save</button>
            <a href="{{ route('admin.organizations.members', $organization) }}" class="console-btn console-btn-ghost">Members</a>
            <a href="{{ route('admin.organizations.index') }}" class="console-btn console-btn-ghost">Back</a>
        </div>
    </form>
@endsection
