@extends('layouts.stream')

@section('title', 'Archive · '.config('app.name'))

@section('vite')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-emerald-400/90">Past broadcasts</p>
        <h1 class="mt-1 text-2xl font-semibold text-white">Archive</h1>
        <p class="mt-1 text-sm text-zinc-400">Listen back to recorded services.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-left text-sm">
            <thead class="bg-zinc-900/80 text-zinc-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Stream</th>
                    <th class="px-4 py-3 font-medium">Recorded</th>
                    <th class="px-4 py-3 font-medium">Duration</th>
                    <th class="px-4 py-3 font-medium">Size</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950/50">
                @forelse ($recordings as $recording)
                    <tr>
                        <td class="px-4 py-3 text-white">
                            {{ $recording->stream->title }}
                            <span class="mt-0.5 block text-xs text-zinc-500">{{ $recording->stream->organization->name }}</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-400">{{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $recording->duration_raw ?: '—' }}</td>
                        <td class="px-4 py-3 text-zinc-500">
                            @if ($recording->size_bytes)
                                {{ number_format($recording->size_bytes / 1024 / 1024, 1) }} MB
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('archive.play', $recording) }}"
                                class="font-medium text-emerald-400 hover:text-emerald-300"
                                target="_blank" rel="noopener">Play</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
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
