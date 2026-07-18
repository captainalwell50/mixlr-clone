@extends('layouts.app')

@section('title', 'New organization')

@section('content')
    <h1 class="console-title">New organization</h1>

    <form method="POST" action="{{ route('admin.organizations.store') }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium text-zinc-300">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-zinc-300">Slug <span class="text-zinc-500">(optional)</span></label>
            <input id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="auto from name"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="console-btn console-btn-primary">Create</button>
            <a href="{{ route('admin.organizations.index') }}" class="console-btn console-btn-ghost hover:bg-zinc-800">Cancel</a>
        </div>
    </form>
@endsection
