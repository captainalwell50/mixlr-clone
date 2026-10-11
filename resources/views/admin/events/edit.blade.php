@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.creator')

@section('title', 'Edit Event · ' . $event->title)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- BREADCRUMB / NAV BACK -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.events.index') }}" class="inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-emerald-400 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            <span>Back to Events & Schedule</span>
        </a>

        <div class="flex items-center gap-2">
            <a href="{{ route('events.show', $event) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition">
                <span>View Event Page</span>
                <span class="sr-only">(opens in new tab)</span>
            </a>
            @if ($event->status->value === 'live')
                <a href="{{ route('admin.streams.studio', $stream) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                    </svg>
                    <span>Open Studio</span>
                </a>
            @endif
        </div>
    </div>

    <!-- PAGE HEADER CARD -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">{{ $event->organization->name }}</span>
                    <span class="text-zinc-600">·</span>
                    @if ($event->status->value === 'live')
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-950 px-2 py-0.5 text-[10px] font-bold text-red-300 border border-red-700/60 uppercase">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-400 animate-pulse"></span>
                            Live Now
                        </span>
                    @elseif ($event->status->value === 'scheduled')
                        <span class="rounded-full bg-emerald-950/80 px-2 py-0.5 text-[10px] font-semibold text-emerald-300 border border-emerald-800/60 uppercase">
                            Scheduled
                        </span>
                    @else
                        <span class="rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-400 uppercase">
                            {{ $event->status->value }}
                        </span>
                    @endif
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ $event->title }}</h1>
            </div>

            <!-- Header Broadcast Action Button -->
            <div class="shrink-0">
                @if ($event->status->value !== 'live')
                    <form method="POST" action="{{ route('admin.events.go-live', $event) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                            </svg>
                            <span>Go Live with Event</span>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.events.end', $event) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-red-900/60 border border-red-700/80 px-4 py-2 text-xs font-bold text-red-200 hover:bg-red-800 transition">
                            <span>End Broadcast</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- SHARE & INGEST DETAILS CARD -->
    <div class="rounded-xl border border-emerald-900/40 bg-emerald-950/20 p-5 space-y-4">
        <div>
            <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Shareable Event URL</h2>
            <p class="text-xs text-zinc-400 mt-0.5">Share this link with your congregation, on social media, or in messaging apps.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <code id="event-url" class="flex-1 break-all rounded-lg bg-zinc-950 px-3 py-2 text-xs text-zinc-300 font-mono border border-zinc-800">{{ route('events.show', $event) }}</code>
            <button
                type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('event-url').textContent); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000)"
                class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-xs font-semibold text-zinc-200 hover:bg-zinc-700 hover:text-white transition"
            >
                Copy
            </button>
        </div>

        <div class="pt-2 border-t border-emerald-900/30 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div class="rounded-lg bg-zinc-900/80 p-3 border border-zinc-800">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 block">OBS RTMP URL</span>
                <code class="text-zinc-300 break-all block mt-0.5 font-mono text-[11px]">{{ $stream->rtmpUrl() }}</code>
            </div>
            <div class="rounded-lg bg-zinc-900/80 p-3 border border-zinc-800">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 block">OBS Stream Key</span>
                <code class="text-amber-300/90 break-all block mt-0.5 font-mono text-[11px]">{{ $stream->rtmpStreamKeyForObs() }}</code>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-800/60 bg-red-950/40 p-4 text-xs text-red-300">
            <div class="font-semibold mb-1">Please correct the following errors:</div>
            <ul class="list-disc list-inside space-y-0.5 text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.events.update', $event) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- 1. EVENT DETAILS -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">1</span>
                    Event Details
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Specify the broadcast channel and title for this service.</p>
            </div>

            <div>
                <label for="organization_id" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">Channel / Organization <span class="text-red-400">*</span></label>
                <select id="organization_id" name="organization_id" required class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" @selected(old('organization_id', $event->organization_id) == $org->id)>{{ $org->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-zinc-500">The broadcasting church or ministry organization that hosts this event.</p>
            </div>

            <div>
                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">Event Title <span class="text-red-400">*</span></label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    required
                    value="{{ old('title', $event->title) }}"
                    class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >
                <p class="mt-1 text-[11px] text-zinc-500">Visible on promotional banners, browser tabs, and social media share previews.</p>
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >{{ old('description', $event->description) }}</textarea>
                <p class="mt-1 text-[11px] text-zinc-500">Optional description displayed underneath the audio player on the event page.</p>
            </div>
        </div>

        <!-- 2. SCHEDULE & TIMING -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">2</span>
                    Schedule & Timing
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Define when your service starts so listeners see an accurate countdown.</p>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="scheduled_at" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">Scheduled Start Time</label>
                    <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-medium text-emerald-400 border border-zinc-700">{{ now()->timezone(\App\Support\LocalTime::tz())->format('T') }} · {{ \App\Support\LocalTime::tz() }}</span>
                </div>
                <input
                    id="scheduled_at"
                    type="datetime-local"
                    name="scheduled_at"
                    value="{{ old('scheduled_at', \App\Support\LocalTime::formValue($event->scheduled_at)) }}"
                    class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >
                <p class="mt-1 text-[11px] text-zinc-500">
                    Input start time in platform broadcast time ({{ now()->timezone(\App\Support\LocalTime::tz())->format('T') }} · {{ \App\Support\LocalTime::tz() }}). Channel and event pages display this broadcast timezone to listeners.
                </p>
            </div>
        </div>

        <!-- 3. ACCESS & PRIVACY -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">3</span>
                    Access & Privacy
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Control who can discover and tune in to this broadcast.</p>
            </div>

            <div>
                <label for="access" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">Access Level <span class="text-red-400">*</span></label>
                <select id="access" name="access" class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    @foreach (['public' => 'Public — Listed in Discover & channel directory', 'unlisted' => 'Unlisted — Accessible to anyone with the link', 'private' => 'Private — Protected with access password'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('access', $event->access->value) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Conditional Password Field -->
            <div id="password-field-container" class="space-y-1.5 transition-all {{ old('access', $event->access->value) === 'private' ? '' : 'hidden' }}" aria-live="polite" aria-hidden="{{ old('access', $event->access->value) === 'private' ? 'false' : 'true' }}">
                <label for="access_password" class="block text-xs font-semibold uppercase tracking-wider text-amber-300">
                    Access Password
                </label>
                <input
                    id="access_password"
                    type="text"
                    name="access_password"
                    value="{{ old('access_password') }}"
                    placeholder="Leave blank to keep existing password..."
                    class="w-full rounded-lg border border-amber-700/60 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                >
                <p class="text-[11px] text-zinc-400">Leave blank to retain current password, or enter a new password for listeners.</p>
            </div>
        </div>

        <!-- 4. ENGAGEMENT -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">4</span>
                    Engagement & Interactivity
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Toggle interactive community features for your live listeners.</p>
            </div>

            <div class="space-y-4">
                <input type="hidden" name="chat_enabled" value="0">
                <label class="flex items-start gap-3 rounded-lg border border-zinc-800/80 bg-zinc-900/30 p-3 hover:border-zinc-700 cursor-pointer transition">
                    <input
                        type="checkbox"
                        name="chat_enabled"
                        value="1"
                        @checked(old('chat_enabled', $event->chat_enabled))
                        class="mt-0.5 rounded border-zinc-700 bg-zinc-800 text-emerald-600 focus:ring-emerald-500"
                    >
                    <div>
                        <div class="text-xs font-semibold text-zinc-200">Enable Live Chat</div>
                        <div class="text-[11px] text-zinc-400">Permit listeners to post real-time chat messages and greetings during the service.</div>
                    </div>
                </label>

                <input type="hidden" name="show_listener_count" value="0">
                <label class="flex items-start gap-3 rounded-lg border border-zinc-800/80 bg-zinc-900/30 p-3 hover:border-zinc-700 cursor-pointer transition">
                    <input
                        type="checkbox"
                        name="show_listener_count"
                        value="1"
                        @checked(old('show_listener_count', $event->show_listener_count))
                        class="mt-0.5 rounded border-zinc-700 bg-zinc-800 text-emerald-600 focus:ring-emerald-500"
                    >
                    <div>
                        <div class="text-xs font-semibold text-zinc-200">Show Listener Counter</div>
                        <div class="text-[11px] text-zinc-400">Display the concurrent number of active listeners on the public player page.</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- FORM ACTIONS -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.events.index') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition">
                Back to Events
            </a>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>Save Changes</span>
            </button>
        </div>
    </form>

    <!-- DANGER ZONE -->
    <div class="rounded-xl border border-red-950/80 bg-red-950/20 p-5 sm:p-6 space-y-4">
        <div>
            <h2 class="text-xs font-bold uppercase tracking-wider text-red-400">Danger Zone</h2>
            <p class="text-xs text-zinc-400 mt-0.5">Permanently delete this scheduled event and its associated stream configuration. This action cannot be undone.</p>
        </div>
        <form method="POST" action="{{ route('admin.events.destroy', $event) }}" onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-800 bg-red-950/60 px-4 py-2 text-xs font-semibold text-red-300 hover:bg-red-900/80 hover:text-white transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                <span>Delete Event</span>
            </button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const accessSelect = document.getElementById('access');
        const passwordContainer = document.getElementById('password-field-container');

        function togglePassword() {
            const isPrivate = accessSelect.value === 'private';
            if (isPrivate) {
                passwordContainer.classList.remove('hidden');
                passwordContainer.setAttribute('aria-hidden', 'false');
            } else {
                passwordContainer.classList.add('hidden');
                passwordContainer.setAttribute('aria-hidden', 'true');
            }
        }

        accessSelect.addEventListener('change', togglePassword);
        togglePassword();
    });
</script>
@endsection
