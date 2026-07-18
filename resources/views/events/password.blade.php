@extends('layouts.stream')

@section('title', 'Private event')

@section('content')
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-amber-400/90">Private</p>
        <h1 class="mt-1 text-2xl font-semibold text-white">{{ $event->title }}</h1>
        <p class="mt-1 text-sm text-zinc-400">Enter the password to listen.</p>
    </div>
    <form method="POST" action="{{ route('events.unlock', $event) }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="block text-sm text-zinc-300">Password</label>
            <input id="password" type="password" name="password" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            @error('password')
                <p class="mt-1 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">Unlock</button>
    </form>
@endsection
