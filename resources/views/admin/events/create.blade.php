@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.creator')

@section('title', 'Schedule Event')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- BREADCRUMB / NAV BACK -->
    <div>
        <a href="{{ route('admin.events.index') }}" class="inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-emerald-400 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            <span>Back to Events & Schedule</span>
        </a>
    </div>

    <!-- PAGE HEADER -->
    <div class="border-b border-zinc-800 pb-5">
        <h1 class="text-2xl font-bold tracking-tight text-white">Schedule Event</h1>
        <p class="text-xs text-zinc-400 mt-1 max-w-xl leading-relaxed">
            Create a dedicated event page with a shareable promotional link. When you go live in Web Studio, this page automatically transitions into your live broadcast player.
        </p>
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

    <form method="POST" action="{{ route('admin.events.store') }}" class="space-y-6">
        @csrf

        <!-- 1. EVENT DETAILS -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">1</span>
                    Event Details
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Specify the broadcast channel and public title for this event.</p>
            </div>

            <div>
                <label for="organization_id" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">Channel / Organization <span class="text-red-400">*</span></label>
                <select id="organization_id" name="organization_id" required class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" @selected(old('organization_id') == $org->id)>{{ $org->name }}</option>
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
                    value="{{ old('title') }}"
                    placeholder="e.g. Sunday Morning Worship & Word"
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
                    placeholder="Provide service details, minister info, scriptures, or theme notes..."
                    class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >{{ old('description') }}</textarea>
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
                    value="{{ old('scheduled_at') }}"
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
                    <option value="public" @selected(old('access', 'public') === 'public')>Public — Listed in Discover & channel directory</option>
                    <option value="unlisted" @selected(old('access') === 'unlisted')>Unlisted — Accessible to anyone with the link</option>
                    <option value="private" @selected(old('access') === 'private')>Private — Protected with access password</option>
                </select>
            </div>

            <!-- Conditional Password Field -->
            <div id="password-field-container" class="space-y-1.5 transition-all {{ old('access') === 'private' ? '' : 'hidden' }}" aria-live="polite" aria-hidden="{{ old('access') === 'private' ? 'false' : 'true' }}">
                <label for="access_password" class="block text-xs font-semibold uppercase tracking-wider text-amber-300">
                    Access Password <span class="text-amber-400">*</span>
                </label>
                <input
                    id="access_password"
                    type="text"
                    name="access_password"
                    value="{{ old('access_password') }}"
                    placeholder="Enter password required to listen..."
                    class="w-full rounded-lg border border-amber-700/60 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                >
                <p class="text-[11px] text-zinc-400">Listeners will be prompted to enter this password before audio playback starts.</p>
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
                <label class="flex items-start gap-3 rounded-lg border border-zinc-800/80 bg-zinc-900/30 p-3 hover:border-zinc-700 cursor-pointer transition">
                    <input
                        type="checkbox"
                        name="chat_enabled"
                        value="1"
                        @checked(old('chat_enabled', true))
                        class="mt-0.5 rounded border-zinc-700 bg-zinc-800 text-emerald-600 focus:ring-emerald-500"
                    >
                    <div>
                        <div class="text-xs font-semibold text-zinc-200">Enable Live Chat</div>
                        <div class="text-[11px] text-zinc-400">Permit listeners to post real-time chat messages and greetings during the service.</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 rounded-lg border border-zinc-800/80 bg-zinc-900/30 p-3 hover:border-zinc-700 cursor-pointer transition">
                    <input
                        type="checkbox"
                        name="show_listener_count"
                        value="1"
                        @checked(old('show_listener_count', true))
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
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>Schedule Event</span>
            </button>
        </div>
    </form>
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
