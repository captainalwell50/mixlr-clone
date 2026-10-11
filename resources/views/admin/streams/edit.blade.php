@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.creator')

@section('title', 'Inspect Stream · ' . $stream->title)

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <!-- BREADCRUMB / NAV BACK -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.streams.index') }}" class="inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-emerald-400 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            <span>Back to Streams</span>
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('listen.stream', $stream) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-300 hover:bg-zinc-700 transition">
                Listen
            </a>
            <a href="{{ route('admin.streams.studio', $stream) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-emerald-800/60 bg-emerald-950/40 px-2.5 py-1 text-xs font-medium text-emerald-300 hover:bg-emerald-900/60 transition">
                Open Studio
            </a>
        </div>
    </div>

    <!-- STREAM DETAIL HEADER CARD -->
    @php
        $isLive = $stream->status === \App\Enums\StreamStatus::Live;
        $listeners = $isLive ? $stream->activeListenerCount() : 0;
        $serviceEvent = $stream->openServiceEvent();
    @endphp
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Stream Inspection</span>
                    @if ($stream->is_public)
                        <span class="rounded bg-zinc-800 px-1.5 py-0.2 text-[10px] text-zinc-300">Public (Discover)</span>
                    @else
                        <span class="rounded bg-zinc-900 px-1.5 py-0.2 text-[10px] text-zinc-500 border border-zinc-800">Unlisted</span>
                    @endif
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ $stream->title }}</h1>
                <p class="text-sm text-emerald-400 mt-0.5">
                    <a href="{{ route('admin.organizations.edit', $stream->organization) }}" class="hover:underline">{{ $stream->organization->name }}</a>
                </p>
                @if ($stream->description)
                    <p class="text-xs text-zinc-400 mt-2 max-w-2xl leading-relaxed">{{ $stream->description }}</p>
                @endif
            </div>

            <!-- Prominent Live / Offline badge -->
            <div class="shrink-0">
                @if ($isLive)
                    <div class="rounded-xl border border-emerald-700/60 bg-emerald-950/50 p-4 text-right shadow-inner">
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-300 border border-emerald-700/60">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            LIVE NOW
                        </div>
                        <div class="mt-2 text-xl font-bold text-white">{{ number_format($listeners) }} <span class="text-xs font-normal text-zinc-400">listeners</span></div>
                        <p class="text-[11px] text-emerald-400/80 mt-0.5">
                            Started: {{ $stream->started_at ? \App\Support\LocalTime::format($stream->started_at, 'H:i · M j') : 'Active' }}
                        </p>
                    </div>
                @else
                    <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 px-4 py-3 text-right">
                        <span class="inline-flex items-center rounded-md bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-400 border border-zinc-700/50">
                            Offline Standby
                        </span>
                        <p class="text-[11px] text-zinc-500 mt-1">Ready for broadcast ingest</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Telemetry snapshot grid -->
        <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 border-t border-zinc-800/80 pt-4 text-xs">
            <div>
                <span class="text-zinc-500 text-[10px] uppercase tracking-wider block">Created</span>
                <span class="text-zinc-300 font-medium">{{ $stream->created_at ? \App\Support\LocalTime::format($stream->created_at, 'M j, Y') : '—' }}</span>
            </div>
            <div>
                <span class="text-zinc-500 text-[10px] uppercase tracking-wider block">Last Live</span>
                <span class="text-zinc-300 font-medium">{{ $stream->started_at ? \App\Support\LocalTime::format($stream->started_at, 'M j, Y H:i') : 'Never recorded' }}</span>
            </div>
            <div>
                <span class="text-zinc-500 text-[10px] uppercase tracking-wider block">Primary Playback</span>
                <span class="font-mono text-zinc-300">{{ $stream->playbackMode() === 'hls' ? 'CDN HLS' : 'WHEP direct' }}</span>
            </div>
            <div>
                <span class="text-zinc-500 text-[10px] uppercase tracking-wider block">Live Chat</span>
                <span class="text-zinc-300">{{ $stream->chat_enabled ? 'Enabled' : 'Disabled' }}</span>
            </div>
        </div>
    </div>

    <!-- TECHNICAL IDENTIFIERS & PROTOCOL ENDPOINTS -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-6 space-y-4">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-300">Technical Identifiers & Endpoints</h2>
            <p class="mt-0.5 text-xs text-zinc-500">Stream identifiers and WebRTC / HLS protocol URLs for this broadcast.</p>
        </div>
        <dl class="space-y-3 text-xs">
            <div>
                <dt class="text-zinc-500 font-medium">Stream UUID</dt>
                <dd class="mt-1 flex items-center gap-2">
                    <code id="tech-uuid" class="block flex-1 font-mono rounded bg-zinc-950 px-2.5 py-1.5 text-zinc-300 text-[11px] select-all">{{ $stream->uuid }}</code>
                    <button type="button" data-copy="tech-uuid" class="rounded border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-zinc-300 hover:bg-zinc-700">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500 font-medium">Media Path</dt>
                <dd class="mt-1 flex items-center gap-2">
                    <code id="tech-path" class="block flex-1 font-mono rounded bg-zinc-950 px-2.5 py-1.5 text-zinc-400 text-[11px] select-all">{{ $stream->mediaPath() }}</code>
                    <button type="button" data-copy="tech-path" class="rounded border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-zinc-300 hover:bg-zinc-700">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500 font-medium">WHEP Monitor URL (Low-latency WebRTC playback)</dt>
                <dd class="mt-1 flex items-center gap-2">
                    <code id="tech-whep" class="block flex-1 font-mono rounded bg-zinc-950 px-2.5 py-1.5 text-zinc-400 text-[11px] select-all truncate">{{ $stream->whepUrl() }}</code>
                    <button type="button" data-copy="tech-whep" class="rounded border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-zinc-300 hover:bg-zinc-700">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500 font-medium">HLS Playlist URL (CDN Delivery)</dt>
                <dd class="mt-1 flex items-center gap-2">
                    <code id="tech-hls" class="block flex-1 font-mono rounded bg-zinc-950 px-2.5 py-1.5 text-zinc-400 text-[11px] select-all truncate">{{ $stream->hlsPlaylistUrl() }}</code>
                    <button type="button" data-copy="tech-hls" class="rounded border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-zinc-300 hover:bg-zinc-700">Copy</button>
                </dd>
            </div>
        </dl>
    </div>

    <!-- SHARE LINKS (Listen, Embed, Studio) -->
    <div class="space-y-4 rounded-xl border border-emerald-900/50 bg-emerald-950/20 p-6">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-400/90">Share Links</h2>
            <p class="mt-1 text-xs text-zinc-500">Listen for the audience, Embed for websites, Studio for browser broadcast.</p>
        </div>
        <dl class="space-y-4 text-sm">
            <div>
                <dt class="text-zinc-500 text-xs">Public Listen Page</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-start">
                    <code id="listen-url" class="block flex-1 break-all rounded bg-zinc-950 px-2.5 py-1.5 text-xs text-zinc-300">{{ route('listen.stream', $stream) }}</code>
                    <button type="button" data-copy="listen-url" class="shrink-0 rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500 text-xs">Embed Player</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-start">
                    <code id="embed-url" class="block flex-1 break-all rounded bg-zinc-950 px-2.5 py-1.5 text-xs text-zinc-300">{{ route('embed.stream', $stream) }}</code>
                    <button type="button" data-copy="embed-url" class="shrink-0 rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Copy</button>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500 text-xs">Signed Studio Link (24 hours valid &middot; Secret)</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-start">
                    <code id="studio-url" class="block flex-1 break-all rounded bg-zinc-950 px-2.5 py-1.5 text-xs text-amber-200/90">{{ $studioUrl }}</code>
                    <button type="button" data-copy="studio-url" class="shrink-0 rounded-lg border border-amber-800/60 px-3 py-1.5 text-xs text-amber-200 hover:bg-amber-950/40">Copy</button>
                </dd>
            </div>
        </dl>
    </div>

    <!-- OBS / RTMP INGEST SETTINGS -->
    @if ($stream->organization?->hasFeature('rtmp_ingest') || auth()->user()?->isAdmin())
        <div class="space-y-4 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">OBS & Desktop RTMP Ingest</h2>
                <p class="mt-1 text-xs text-zinc-500">In OBS: Settings &rarr; Stream &rarr; Custom. Server = RTMP URL, Stream Key = the key below.</p>
            </div>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-zinc-500 text-xs">RTMP Server</dt>
                    <dd class="mt-1 flex flex-col gap-2 sm:flex-row">
                        <code id="rtmp-url" class="block flex-1 break-all rounded bg-zinc-950 px-2.5 py-1.5 text-xs text-zinc-300">{{ $stream->rtmpUrl() }}</code>
                        <button type="button" data-copy="rtmp-url" class="shrink-0 rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Copy</button>
                    </dd>
                </div>
                <div>
                    <dt class="text-zinc-500 text-xs">Stream Key (Includes authentication &middot; Secret)</dt>
                    <dd class="mt-1 flex flex-col gap-2 sm:flex-row">
                        <code id="rtmp-key" class="block flex-1 break-all rounded bg-zinc-950 px-2.5 py-1.5 text-xs text-amber-200/90">{{ $stream->rtmpStreamKeyForObs() }}</code>
                        <button type="button" data-copy="rtmp-key" class="shrink-0 rounded-lg border border-amber-800/60 px-3 py-1.5 text-xs text-amber-200 hover:bg-amber-950/40">Copy</button>
                    </dd>
                </div>
                <div>
                    <dt class="text-zinc-500 text-xs">Raw Stream Key</dt>
                    <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <code id="raw-key" class="block flex-1 break-all rounded bg-zinc-950 px-2.5 py-1.5 font-mono text-xs text-zinc-400">{{ $stream->stream_key }}</code>
                        <form method="POST" action="{{ route('admin.streams.regenerate-key', $stream) }}" onsubmit="return confirm('Regenerate stream key? OBS and WHIP credentials will change.');">
                            @csrf
                            <button type="submit" class="rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800">Regenerate</button>
                        </form>
                    </dd>
                </div>
            </dl>
        </div>
    @endif

    <!-- STREAM CONFIGURATION FORM -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-300 mb-4">Stream Properties & Metadata</h2>
        <form method="POST" action="{{ route('admin.streams.update', $stream) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="organization_id" class="block text-xs font-medium text-zinc-300">Organization</label>
                    <select id="organization_id" name="organization_id" required
                        class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white focus:border-emerald-600 focus:outline-none">
                        @foreach ($organizations as $org)
                            <option value="{{ $org->id }}" @selected(old('organization_id', $stream->organization_id) == $org->id)>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-zinc-300">Status State</label>
                    <select id="status" name="status" required
                        class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white focus:border-emerald-600 focus:outline-none">
                        <option value="offline" @selected(old('status', $stream->status->value) === 'offline')>offline</option>
                        <option value="live" @selected(old('status', $stream->status->value) === 'live')>live</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="title" class="block text-xs font-medium text-zinc-300">Stream Title</label>
                <input id="title" type="text" name="title" value="{{ old('title', $stream->title) }}" required
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white focus:border-emerald-600 focus:outline-none">
            </div>

            <div>
                <label for="description" class="block text-xs font-medium text-zinc-300">Description</label>
                <textarea id="description" name="description" rows="2"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white focus:border-emerald-600 focus:outline-none">{{ old('description', $stream->description) }}</textarea>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 pt-2">
                <label class="flex items-center gap-2 text-xs text-zinc-300">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $stream->is_public)) class="rounded border-zinc-600 text-emerald-600">
                    List on Discover directory
                </label>
                <label class="flex items-center gap-2 text-xs text-zinc-300">
                    <input type="hidden" name="chat_enabled" value="0">
                    <input type="checkbox" name="chat_enabled" value="1" @checked(old('chat_enabled', $stream->chat_enabled)) class="rounded border-zinc-600 text-emerald-600">
                    Enable live audience chat
                </label>
            </div>

            <div class="flex items-center gap-3 pt-3 border-t border-zinc-800">
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition">
                    Save Changes
                </button>
                <a href="{{ route('admin.streams.index') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs font-medium text-zinc-300 hover:bg-zinc-700 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- RECENT RECORDINGS -->
    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900/40">
        <div class="border-b border-zinc-800 bg-zinc-900/80 px-4 py-3 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Recent Stream Recordings ({{ $stream->recordings->count() }})</h2>
        </div>
        <table class="min-w-full divide-y divide-zinc-800 text-left text-xs">
            <thead class="text-zinc-400 bg-zinc-900/50">
                <tr>
                    <th class="px-4 py-2 font-medium">Archive File</th>
                    <th class="px-4 py-2 font-medium">Completed</th>
                    <th class="px-4 py-2 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950/50">
                @forelse ($stream->recordings as $recording)
                    <tr>
                        <td class="px-4 py-2 font-mono text-[11px] text-zinc-300">{{ \Illuminate\Support\Str::limit($recording->relative_path, 48) }}</td>
                        <td class="px-4 py-2 text-zinc-500">{{ \App\Support\LocalTime::format($recording->completed_at, 'M j, H:i') }}</td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('archive.play', $recording) }}" class="text-zinc-400 hover:text-white" target="_blank">Play</a>
                            <a href="{{ route('admin.recordings.download', $recording) }}" class="text-emerald-400 hover:text-emerald-300">Download</a>
                            <form method="POST" action="{{ route('admin.recordings.destroy', $recording) }}" class="inline" onsubmit="return confirm('Delete this recording permanently?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-400 hover:text-rose-300">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No recordings stored yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- DANGER ZONE: DELETE STREAM -->
    <div class="rounded-xl border border-red-950/60 bg-red-950/10 p-6 flex items-center justify-between">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-red-400">Delete Stream</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Permanently deletes this broadcast path and its credentials.</p>
        </div>
        <form method="POST" action="{{ route('admin.streams.destroy', $stream) }}" onsubmit="return confirm('Delete this stream permanently? This action cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-900 bg-red-950/60 px-3 py-1.5 text-xs font-medium text-red-200 hover:bg-red-900 transition">
                Delete Stream
            </button>
        </form>
    </div>

    <!-- Clipboard copy script -->
    <script>
        document.querySelectorAll('[data-copy]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const targetId = btn.getAttribute('data-copy');
                const el = document.getElementById(targetId);
                if (!el) return;
                await navigator.clipboard.writeText(el.textContent.trim());
                const prev = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => { btn.textContent = prev; }, 1500);
            });
        });
    </script>
</div>
@endsection
