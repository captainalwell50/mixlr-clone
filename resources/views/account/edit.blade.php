@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : ((auth()->user()?->relationLoaded('organizations') ? auth()->user()->organizations->isNotEmpty() : auth()->user()?->organizations()->exists()) ? 'layouts.creator' : 'layouts.app'))

@section('title', 'Account & Security')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- PAGE HEADER -->
    <div class="border-b border-zinc-800 pb-5">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">
                {{ auth()->user()?->isAdmin() ? 'Platform Admin Profile' : 'Church Creator Profile' }}
            </span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mt-1">Account & Security</h1>
        <p class="text-xs text-zinc-400 mt-1 max-w-xl leading-relaxed">
            Manage your personal profile details, authentication credentials, and platform security settings.
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-800/60 bg-emerald-950/40 p-4 text-xs font-medium text-emerald-300 flex items-center gap-2">
            <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-800/60 bg-red-950/40 p-4 text-xs font-medium text-red-300 flex items-center gap-2">
            <svg class="h-4 w-4 text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- PROFILE & PASSWORD FORM -->
    <form method="POST" action="{{ route('account.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- 1. PERSONAL PROFILE -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">1</span>
                    Profile Information
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Your personal name and sign-in credentials.</p>
            </div>

            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">
                    Full Name <span class="text-red-400">*</span>
                </label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    required
                    value="{{ old('name', $user->name) }}"
                    class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >
                @error('name')
                    <span class="mt-1 block text-xs text-red-400">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                    Email Address
                </label>
                <input
                    id="email"
                    type="email"
                    value="{{ $user->email }}"
                    disabled
                    class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950/70 px-3.5 py-2.5 text-xs text-zinc-400 cursor-not-allowed"
                >
                <p class="mt-1 text-[11px] text-zinc-500">Contact a platform administrator to update your account email address.</p>
            </div>
        </div>

        <!-- 2. SECURITY & PASSWORD -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">2</span>
                    Security & Password
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Update your password to protect your broadcasting account.</p>
            </div>

            <div>
                <label for="current_password" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">
                    Current Password <span class="text-red-400">*</span>
                </label>
                <input
                    id="current_password"
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >
                @error('current_password')
                    <span class="mt-1 block text-xs text-red-400">{{ $message }}</span>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">
                        New Password <span class="text-red-400">*</span>
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    >
                    @error('password')
                        <span class="mt-1 block text-xs text-red-400">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-zinc-300">
                        Confirm New Password <span class="text-red-400">*</span>
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        class="mt-1.5 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    >
                </div>
            </div>
        </div>

        <!-- 3. INTERFACE APPEARANCE -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5 sm:p-6 space-y-4">
            <div class="border-b border-zinc-800/80 pb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 text-[10px] font-bold text-emerald-400 border border-emerald-800/60">3</span>
                    Interface Appearance
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">Customize your visual console workspace. Automatically adapts to system preference or choose an intentional mode.</p>
            </div>

            @include('partials.theme-selector', ['mode' => 'expanded'])
        </div>

        <div class="flex justify-end pt-1">
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 transition focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>Save account</span>
            </button>
        </div>
    </form>

    <!-- LEGAL & SUPPORT -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 sm:p-6 space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Legal & Support</h2>
        <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-400">
            <a class="text-emerald-400 hover:text-emerald-300 transition underline underline-offset-2" href="{{ route('legal.privacy') }}">Privacy Policy</a>
            <span class="text-zinc-600">·</span>
            <a class="text-emerald-400 hover:text-emerald-300 transition underline underline-offset-2" href="{{ route('legal.terms') }}">Terms of Service</a>
            <span class="text-zinc-600">·</span>
            <a class="text-emerald-400 hover:text-emerald-300 transition underline underline-offset-2" href="{{ route('legal.support') }}">Support</a>
            <span class="text-zinc-500">({{ $supportEmail }})</span>
        </div>
    </div>

    <!-- DANGER ZONE: ACCOUNT DELETION -->
    <div class="rounded-xl border border-red-900/60 bg-red-950/20 p-5 sm:p-6 space-y-5">
        <div class="border-b border-red-900/40 pb-3">
            <div class="flex items-center gap-2 text-red-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <h2 class="text-sm font-bold uppercase tracking-wider text-red-300">Danger Zone — Delete Account</h2>
            </div>
            <p class="text-xs text-red-200/90 mt-1 leading-relaxed">
                Permanently removes your personal login credentials, profile, personal API tokens, and followed channels. This action cannot be reversed.
            </p>
            <div class="mt-3 rounded-lg border border-red-900/40 bg-red-950/30 p-3.5 space-y-2 text-[11px] text-red-200/90 leading-relaxed">
                <p>
                    <strong class="text-red-200 font-semibold">Organizations &amp; Content Remain:</strong> Your organization memberships are detached upon deletion. Organizations, live streams, recordings, podcasts, and digital library content are <em>not</em> deleted and remain intact.
                </p>
                <p>
                    <strong class="text-red-200 font-semibold">Ownership Transfer:</strong> Ownership does not transfer automatically. If you are the sole owner of an organization, the organization and its recordings remain preserved and unowned until a platform administrator reassigns ownership. If other owners or managers exist, they will continue managing the organization uninterrupted.
                </p>
                <p>
                    <strong class="text-red-200 font-semibold">Organization Admins &amp; Members:</strong> Your membership is detached, leaving the organization and its leadership unaffected.
                </p>
                <p>
                    <strong class="text-red-200 font-semibold">Platform Super Admins:</strong> Account deletion is blocked if you are the last platform administrator.
                </p>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('account.destroy') }}"
            class="space-y-4 max-w-md"
            onsubmit="return confirm('Delete your Sound Mix Live account permanently? This action cannot be reversed.');"
        >
            @csrf
            @method('DELETE')

            <div>
                <label for="delete_confirm" class="block text-xs font-semibold text-zinc-300">
                    Type <strong class="text-red-400 font-bold uppercase">DELETE</strong> to confirm
                </label>
                <input
                    id="delete_confirm"
                    name="confirm"
                    type="text"
                    required
                    autocomplete="off"
                    placeholder="Type DELETE here..."
                    class="mt-1.5 w-full rounded-lg border border-red-900/60 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                >
                @error('confirm')
                    <span class="mt-1 block text-xs text-red-400">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="delete_password" class="block text-xs font-semibold text-zinc-300">
                    Confirm Your Password
                </label>
                <input
                    id="delete_password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter current password..."
                    class="mt-1.5 w-full rounded-lg border border-red-900/60 bg-zinc-900 px-3.5 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                >
                @error('password')
                    <span class="mt-1 block text-xs text-red-400">{{ $message }}</span>
                @enderror
            </div>

            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg border border-red-800 bg-red-950 px-4 py-2.5 text-xs font-bold text-red-300 hover:bg-red-900/80 hover:text-white transition focus:outline-none focus:ring-2 focus:ring-red-500"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                <span>Delete my account</span>
            </button>
        </form>
    </div>
</div>
@endsection
