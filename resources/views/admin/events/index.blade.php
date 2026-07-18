@extends('layouts.app')

@section('title', 'Events')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">Events</h1>
            <p class="console-lead">Schedule, share, and go live from one place.</p>
        </div>
        <a href="{{ route('admin.events.create') }}" class="console-btn console-btn-primary">Schedule event</a>
    </div>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>When</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td class="text-[var(--stage-cream)]">{{ $event->title }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $event->organization->name }}</td>
                        <td>
                            <span class="console-pill {{ $event->status->value === 'live' ? 'is-live' : '' }}">
                                {{ $event->status->value }}
                            </span>
                        </td>
                        <td class="text-[var(--stage-muted)]">{{ $event->scheduled_at?->format('M j, H:i') ?: '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('events.show', $event) }}" class="console-muted-link" target="_blank">Page</a>
                            <span class="text-[var(--stage-muted)]">·</span>
                            <a href="{{ route('admin.events.edit', $event) }}" class="console-link">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-[var(--stage-muted)]">
                            No events yet. Schedule one to get a shareable link.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $events->links() }}</div>
@endsection
