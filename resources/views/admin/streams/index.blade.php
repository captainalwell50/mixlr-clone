@extends('layouts.app')

@section('title', 'Streams')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">Streams</h1>
            <p class="console-lead">Broadcast paths, studio links, and embeds.</p>
        </div>
        <a href="{{ route('admin.streams.create') }}" class="console-btn console-btn-primary">New stream</a>
    </div>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>UUID</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($streams as $stream)
                    <tr>
                        <td class="text-[var(--stage-cream)]">{{ $stream->title }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $stream->organization->name }}</td>
                        <td>
                            <span class="console-pill {{ $stream->status === \App\Enums\StreamStatus::Live ? 'is-live' : '' }}">
                                {{ $stream->status->value }}
                            </span>
                        </td>
                        <td class="font-mono text-xs text-[var(--stage-muted)]">{{ \Illuminate\Support\Str::limit($stream->uuid, 13, '…') }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('listen.stream', $stream) }}" class="console-muted-link" target="_blank">Listen</a>
                            <span class="text-[var(--stage-muted)]">·</span>
                            <a href="{{ route('embed.stream', $stream) }}" class="console-muted-link" target="_blank">Embed</a>
                            <span class="text-[var(--stage-muted)]">·</span>
                            <a href="{{ route('admin.streams.studio', $stream) }}" class="console-muted-link" target="_blank">Studio</a>
                            <span class="text-[var(--stage-muted)]">·</span>
                            <a href="{{ route('admin.streams.edit', $stream) }}" class="console-link">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-[var(--stage-muted)]">No streams yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $streams->links() }}
    </div>
@endsection
