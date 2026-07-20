@extends('layouts.app')

@section('title', 'Recorded Audio · '.config('app.name', 'Live Mix Audio'))

@section('content')
    <p class="site-section-label">Past broadcasts</p>
    <h1 class="console-title mt-2">Recorded Audio</h1>
    <p class="console-lead">Listen back to recorded services.</p>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Stream</th>
                    <th>Recorded</th>
                    <th>Duration</th>
                    <th>Size</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recordings as $recording)
                    <tr>
                        <td>
                            <span class="text-[var(--stage-cream)]">{{ $recording->stream->title }}</span>
                            <span class="mt-0.5 block text-xs text-[var(--stage-muted)]">{{ $recording->stream->organization->name }}</span>
                        </td>
                        <td class="text-[var(--stage-muted)]">{{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $recording->duration_raw ?: '—' }}</td>
                        <td class="text-[var(--stage-muted)]">
                            @if ($recording->size_bytes)
                                {{ number_format($recording->size_bytes / 1024 / 1024, 1) }} MB
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('archive.play', $recording) }}" class="console-link" target="_blank" rel="noopener">Play</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-[var(--stage-muted)]">
                            No recordings yet. Past broadcasts will show up here after a live service ends.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $recordings->links() }}
    </div>
@endsection
