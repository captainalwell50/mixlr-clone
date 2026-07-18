@extends('layouts.app')

@section('title', 'Edit stream')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">{{ $stream->title }}</h1>
            <p class="mt-1 text-sm text-zinc-400">{{ $stream->organization->name }}</p>
        </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide
            {{ $stream->status === \App\Enums\StreamStatus::Live ? 'bg-emerald-950 text-emerald-300 ring-1 ring-emerald-800' : 'bg-zinc-800 text-zinc-400 ring-1 ring-zinc-700' }}">
            @if ($stream->status === \App\Enums\StreamStatus::Live)
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
            @endif
            {{ $stream->status->value }}
        </span>
    </div>

    <div class="mt-8 space-y-4 rounded-xl border border-emerald-900/50 bg-emerald-950/20 p-6">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-400/90">Share these links</h2>
            <p class="mt-1 text-xs text-zinc-500">Listen for the audience, Studio for browser broadcast, OBS for desktop ingest.</p>
        </div>
        <dl class="space-y-4 text-sm">
            <div>
                <dt class="text-zinc-500">Listen</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-start">
                    <code id="listen-url" class="block flex-1 break-all rounded bg-zinc-950 px-2 py-1.5 text-xs text-zinc-300">{{ route('listen.stream', $stream) }}</code>
                    <button type="button" data-copy="listen-url" class="shrink-0 rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500">Embed</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-start">
                    <code id="embed-url" class="block flex-1 break-all rounded bg-zinc-950 px-2 py-1.5 text-xs text-zinc-300">{{ route('embed.stream', $stream) }}</code>
                    <button type="button" data-copy="embed-url" class="shrink-0 rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500">Studio (signed, 24h — secret)</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-start">
                    <code id="studio-url" class="block flex-1 break-all rounded bg-zinc-950 px-2 py-1.5 text-xs text-amber-200/90">{{ $studioUrl }}</code>
                    <button type="button" data-copy="studio-url" class="shrink-0 rounded-lg border border-amber-800/60 px-3 py-1.5 text-xs text-amber-200 hover:bg-amber-950/40">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500">Admin studio</dt>
                <dd class="mt-1">
                    <a href="{{ route('admin.streams.studio', $stream) }}" target="_blank" rel="noopener"
                        class="inline-flex rounded-lg bg-zinc-800 px-3 py-1.5 text-sm text-white hover:bg-zinc-700">Open studio</a>
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 space-y-4 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">OBS / RTMP</h2>
            <p class="mt-1 text-xs text-zinc-500">In OBS: Settings → Stream → Custom. Server = RTMP URL, Stream Key = the key below (includes pass).</p>
        </div>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-zinc-500">Server</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row">
                    <code id="rtmp-url" class="block flex-1 break-all rounded bg-zinc-950 px-2 py-1.5 text-xs text-zinc-300">{{ $stream->rtmpUrl() }}</code>
                    <button type="button" data-copy="rtmp-url" class="shrink-0 rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500">Stream key (secret)</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row">
                    <code id="rtmp-key" class="block flex-1 break-all rounded bg-zinc-950 px-2 py-1.5 text-xs text-amber-200/90">{{ $stream->rtmpStreamKeyForObs() }}</code>
                    <button type="button" data-copy="rtmp-key" class="shrink-0 rounded-lg border border-amber-800/60 px-3 py-1.5 text-xs text-amber-200 hover:bg-amber-950/40">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500">Raw stream key</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <code id="raw-key" class="block flex-1 break-all rounded bg-zinc-950 px-2 py-1.5 font-mono text-xs text-zinc-400">{{ $stream->stream_key }}</code>
                    <form method="POST" action="{{ route('admin.streams.regenerate-key', $stream) }}" onsubmit="return confirm('Regenerate stream key? OBS and WHIP credentials will change.');">
                        @csrf
                        <button type="submit" class="rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Regenerate</button>
                    </form>
                </dd>
            </div>
        </dl>
    </div>

    <form method="POST" action="{{ route('admin.streams.update', $stream) }}" class="mt-10 max-w-2xl space-y-6">
        @csrf
        @method('PUT')
        <div>
            <label for="organization_id" class="block text-sm font-medium text-zinc-300">Organization</label>
            <select id="organization_id" name="organization_id" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                @foreach ($organizations as $org)
                    <option value="{{ $org->id }}" @selected(old('organization_id', $stream->organization_id) == $org->id)>{{ $org->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="title" class="block text-sm font-medium text-zinc-300">Title</label>
            <input id="title" type="text" name="title" value="{{ old('title', $stream->title) }}" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-zinc-300">Description</label>
            <textarea id="description" name="description" rows="2"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">{{ old('description', $stream->description) }}</textarea>
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-zinc-300">Status</label>
            <select id="status" name="status" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                <option value="offline" @selected(old('status', $stream->status->value) === 'offline')>offline</option>
                <option value="live" @selected(old('status', $stream->status->value) === 'live')>live</option>
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="is_public" value="0">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $stream->is_public)) class="rounded border-zinc-600">
            List on Discover
        </label>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="chat_enabled" value="0">
            <input type="checkbox" name="chat_enabled" value="1" @checked(old('chat_enabled', $stream->chat_enabled)) class="rounded border-zinc-600">
            Enable live chat
        </label>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save</button>
            <a href="{{ route('admin.streams.index') }}" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">Back</a>
        </div>
    </form>

    <div class="mt-10 overflow-hidden rounded-xl border border-zinc-800">
        <div class="border-b border-zinc-800 bg-zinc-900/80 px-4 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Recent recordings</h2>
        </div>
        <table class="min-w-full divide-y divide-zinc-800 text-left text-sm">
            <thead class="text-zinc-400">
                <tr>
                    <th class="px-4 py-2 font-medium">File</th>
                    <th class="px-4 py-2 font-medium">Completed</th>
                    <th class="px-4 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950/50">
                @forelse ($stream->recordings as $recording)
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs text-zinc-300">{{ \Illuminate\Support\Str::limit($recording->relative_path, 48) }}</td>
                        <td class="px-4 py-2 text-zinc-500">{{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, H:i') }}</td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('archive.play', $recording) }}" class="text-zinc-400 hover:text-white" target="_blank">Play</a>
                            <a href="{{ route('admin.recordings.download', $recording) }}" class="text-emerald-400 hover:text-emerald-300">Download</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No segments indexed yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('admin.streams.destroy', $stream) }}" class="mt-8" onsubmit="return confirm('Delete this stream?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg border border-red-900 bg-red-950/40 px-4 py-2 text-sm text-red-200 hover:bg-red-950/70">Delete stream</button>
    </form>

    <script>
        document.querySelectorAll('[data-copy]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const el = document.getElementById(btn.getAttribute('data-copy'));
                if (!el) return;
                await navigator.clipboard.writeText(el.textContent.trim());
                const prev = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(() => { btn.textContent = prev; }, 1500);
            });
        });
    </script>
@endsection
