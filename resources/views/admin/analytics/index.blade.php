@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-white">Analytics</h1>
        <div class="flex gap-2 text-sm">
            @foreach ([7, 30, 90] as $d)
                <a href="{{ route('admin.analytics.index', ['days' => $d]) }}"
                    class="rounded-lg px-3 py-1.5 {{ $days === $d ? 'bg-emerald-600 text-white' : 'border border-zinc-700 text-zinc-400' }}">{{ $d }}d</a>
            @endforeach
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-800 p-4">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Unique listeners</p>
            <p class="mt-1 text-2xl font-semibold text-white">{{ number_format($uniqueListeners) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-800 p-4">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Hearts</p>
            <p class="mt-1 text-2xl font-semibold text-white">{{ number_format($hearts) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-800 p-4">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Chat messages</p>
            <p class="mt-1 text-2xl font-semibold text-white">{{ number_format($chats) }}</p>
        </div>
    </div>

    <div class="mt-10 overflow-hidden rounded-xl border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-left text-sm">
            <thead class="bg-zinc-900/80 text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Event</th>
                    <th class="px-4 py-3">Channel</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Unique listeners</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($events as $event)
                    <tr>
                        <td class="px-4 py-3 text-white">
                            <a href="{{ route('admin.events.edit', $event) }}" class="hover:text-emerald-300">{{ $event->title }}</a>
                        </td>
                        <td class="px-4 py-3 text-zinc-400">{{ $event->organization->name }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $event->status->value }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $perEvent[$event->id] ?? 0 }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No events in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
