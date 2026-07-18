@extends('layouts.app')

@section('title', 'Streams')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-white">Streams</h1>
        <a href="{{ route('admin.streams.create') }}"
            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
            New stream
        </a>
    </div>

    <div class="mt-8 overflow-hidden rounded-xl border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-left text-sm">
            <thead class="bg-zinc-900/80 text-zinc-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Title</th>
                    <th class="px-4 py-3 font-medium">Organization</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">UUID</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950/50">
                @forelse ($streams as $stream)
                    <tr>
                        <td class="px-4 py-3 text-white">{{ $stream->title }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $stream->organization->name }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $stream->status === \App\Enums\StreamStatus::Live ? 'bg-emerald-950 text-emerald-300' : 'bg-zinc-800 text-zinc-400' }}">
                                {{ $stream->status->value }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-zinc-500">{{ \Illuminate\Support\Str::limit($stream->uuid, 13, '…') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('listen.stream', $stream) }}" class="text-zinc-400 hover:text-white" target="_blank">Listen</a>
                            <span class="text-zinc-600">·</span>
                            <a href="{{ route('embed.stream', $stream) }}" class="text-zinc-400 hover:text-white" target="_blank">Embed</a>
                            <span class="text-zinc-600">·</span>
                            <a href="{{ route('admin.streams.studio', $stream) }}" class="text-zinc-400 hover:text-white" target="_blank">Studio</a>
                            <span class="text-zinc-600">·</span>
                            <a href="{{ route('admin.streams.edit', $stream) }}" class="text-emerald-400 hover:text-emerald-300">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No streams yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $streams->links() }}
    </div>
@endsection
