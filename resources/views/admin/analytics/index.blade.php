@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">Analytics</h1>
            <p class="console-lead">Listener reach for your recent events.</p>
        </div>
        <div class="console-actions">
            @foreach ([7, 30, 90] as $d)
                <a href="{{ route('admin.analytics.index', ['days' => $d]) }}"
                    class="console-btn {{ $days === $d ? 'console-btn-primary' : 'console-btn-ghost' }}">{{ $d }}d</a>
            @endforeach
        </div>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="console-stat">
            <dt>Unique listeners</dt>
            <dd>{{ number_format($uniqueListeners) }}</dd>
        </div>
        <div class="console-stat">
            <dt>Hearts</dt>
            <dd>{{ number_format($hearts) }}</dd>
        </div>
        <div class="console-stat">
            <dt>Chat messages</dt>
            <dd>{{ number_format($chats) }}</dd>
        </div>
    </dl>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Unique listeners</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td>
                            <a href="{{ route('admin.events.edit', $event) }}" class="console-link">{{ $event->title }}</a>
                        </td>
                        <td class="text-[var(--stage-muted)]">{{ $event->organization->name }}</td>
                        <td>
                            <span class="console-pill {{ $event->status->value === 'live' ? 'is-live' : '' }}">
                                {{ $event->status->value }}
                            </span>
                        </td>
                        <td>{{ $perEvent[$event->id] ?? 0 }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-[var(--stage-muted)]">No events in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
