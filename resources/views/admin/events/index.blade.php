@extends('layouts.app')

@section('title', 'Events')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-white">Events</h1>
        <a href="{{ route('admin.events.create') }}"
            class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Schedule event</a>
    </div>

    <div class="mt-8 overflow-hidden rounded-xl border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-left text-sm">
            <thead class="bg-zinc-900/80 text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Channel</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($events as $event)
                    <tr>
                        <td class="px-4 py-3 text-white">{{ $event->title }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $event->organization->name }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $event->status->value === 'live' ? 'bg-emerald-950 text-emerald-300' : 'bg-zinc-800 text-zinc-400' }}">
                                {{ $event->status->value }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ $event->scheduled_at?->format('M j, H:i') ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('events.show', $event) }}" class="text-zinc-400 hover:text-white" target="_blank">Page</a>
                            <span class="text-zinc-600">·</span>
                            <a href="{{ route('admin.events.edit', $event) }}" class="text-emerald-400 hover:text-emerald-300">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No events yet. Schedule one to get a shareable link.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $events->links() }}</div>
@endsection
