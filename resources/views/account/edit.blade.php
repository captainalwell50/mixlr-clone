@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : ((auth()->user()?->relationLoaded('organizations') ? auth()->user()->organizations->isNotEmpty() : auth()->user()?->organizations()->exists()) ? 'layouts.creator' : 'layouts.app'))

@section('title', 'Account & Security')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- PAGE HEADER -->
    <div class="border-b border-zinc-200 dark:border-zinc-800 pb-5">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                {{ auth()->user()?->isAdmin() ? 'Platform Admin Profile' : 'Creator Profile' }}
            </span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white mt-1">Account & Security</h1>
        <p class="text-xs text-slate-600 dark:text-zinc-400 mt-1 max-w-xl leading-relaxed">
            Manage your personal profile details, authentication credentials, and platform security settings.
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 p-4 text-xs font-medium text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
            <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 dark:border-red-800/60 bg-red-50 dark:bg-red-950/40 p-4 text-xs font-medium text-red-800 dark:text-red-300 flex items-center gap-2">
            <svg class="h-4 w-4 text-red-600 dark:text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- 1. PERSONAL PROFILE -->
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50 p-5 sm:p-6 space-y-5 shadow-sm dark:shadow-none">
        <div class="border-b border-zinc-100 dark:border-zinc-800/80 pb-3">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950 text-[10px] font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">1</span>
                Profile Information
            </h2>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Your personal name and sign-in credentials.</p>
        </div>

        <div>
            <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-zinc-300">
                Full Name
            </label>
            <input
                id="name"
                name="profile_name"
                type="text"
                readonly
                value="{{ old('name', $user->name) }}"
                class="auth-input cursor-not-allowed mt-1.5"
            >
        </div>

        <div>
            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-zinc-400">
                Email Address
            </label>
            <input
                id="email"
                type="email"
                value="{{ $user->email }}"
                disabled
                class="auth-input cursor-not-allowed mt-1.5"
            >
            <p class="mt-1 text-[11px] text-slate-500 dark:text-zinc-500">Contact a platform administrator to update your account email address.</p>
        </div>
    </div>

    <!-- 2. PASSWORD & SECURITY -->
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50 p-5 sm:p-6 space-y-5 shadow-sm dark:shadow-none">
        <div class="border-b border-zinc-100 dark:border-zinc-800/80 pb-3">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950 text-[10px] font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">2</span>
                Password &amp; Security
            </h2>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Security & Password: Update your credentials to protect your broadcasting account.</p>
        </div>

        <form method="POST" action="{{ route('account.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="name" value="{{ old('name', $user->name) }}">

            <div class="auth-field-group">
                <label for="current_password" class="auth-label text-slate-700 dark:text-zinc-300">
                    Current password <span class="text-red-500 dark:text-red-400">*</span>
                </label>
                <div class="auth-input-password-wrap">
                    <input
                        id="current_password"
                        type="password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter current password..."
                        aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}"
                        class="auth-input @error('current_password') is-invalid @enderror"
                    >
                    <button
                        type="button"
                        class="auth-password-toggle"
                        aria-label="Show current password"
                        title="Show current password"
                        onclick="toggleAuthPassword(this)"
                    >
                        <svg class="auth-eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg class="auth-eye-closed hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('current_password')
                    <p class="auth-error-msg" role="alert">
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="auth-field-group">
                    <label for="password" class="auth-label text-slate-700 dark:text-zinc-300">
                        New password <span class="text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="auth-input-password-wrap">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            class="auth-input @error('password') is-invalid @enderror"
                        >
                        <button
                            type="button"
                            class="auth-password-toggle"
                            aria-label="Show new password"
                            title="Show new password"
                            onclick="toggleAuthPassword(this)"
                        >
                            <svg class="auth-eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg class="auth-eye-closed hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="auth-error-msg" role="alert">
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <div class="auth-field-group">
                    <label for="password_confirmation" class="auth-label text-slate-700 dark:text-zinc-300">
                        Confirm new password <span class="text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="auth-input-password-wrap">
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="auth-input"
                        >
                        <button
                            type="button"
                            class="auth-password-toggle"
                            aria-label="Show password confirmation"
                            title="Show password confirmation"
                            onclick="toggleAuthPassword(this)"
                        >
                            <svg class="auth-eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg class="auth-eye-closed hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button
                    type="submit"
                    class="auth-btn-primary sm:w-auto px-6 h-11 text-xs"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>Update password</span>
                </button>
            </div>
        </form>
    </div>

    <!-- 3. INTERFACE APPEARANCE -->
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50 p-5 sm:p-6 space-y-4 shadow-sm dark:shadow-none">
        <div class="border-b border-zinc-100 dark:border-zinc-800/80 pb-3">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950 text-[10px] font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">3</span>
                Interface Appearance
            </h2>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Customize your visual console workspace. Automatically adapts to system preference or choose an intentional mode.</p>
        </div>

        @include('partials.theme-selector', ['mode' => 'expanded'])
    </div>

    <!-- LEGAL & SUPPORT -->
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white/70 dark:bg-zinc-900/40 p-5 sm:p-6 space-y-3 shadow-sm dark:shadow-none">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-400">Legal & Support</h2>
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600 dark:text-zinc-400">
            <a class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition underline underline-offset-2" href="{{ route('legal.privacy') }}">Privacy Policy</a>
            <span class="text-slate-300 dark:text-zinc-600">·</span>
            <a class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition underline underline-offset-2" href="{{ route('legal.terms') }}">Terms of Service</a>
            <span class="text-slate-300 dark:text-zinc-600">·</span>
            <a class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition underline underline-offset-2" href="{{ route('legal.support') }}">Support</a>
            <span class="text-slate-500 dark:text-zinc-500">({{ $supportEmail ?? config('app.support_email', 'support@soundmix.live') }})</span>
        </div>
    </div>

    <!-- DANGER ZONE: ACCOUNT DELETION -->
    <div class="rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50/60 dark:bg-red-950/20 p-5 sm:p-6 space-y-5">
        <div class="border-b border-red-200/80 dark:border-red-900/40 pb-3">
            <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <h2 class="text-sm font-bold uppercase tracking-wider text-red-700 dark:text-red-300">Danger Zone — Delete Account</h2>
            </div>
            <p class="text-xs text-red-700/90 dark:text-red-200/90 mt-1 leading-relaxed">
                Permanently removes your personal login credentials, profile, personal API tokens, and followed channels. This action cannot be reversed.
            </p>
            <div class="mt-3 rounded-lg border border-red-200 dark:border-red-900/40 bg-white/80 dark:bg-red-950/30 p-3.5 space-y-2 text-[11px] text-red-800 dark:text-red-200/90 leading-relaxed">
                <p>
                    <strong class="text-red-900 dark:text-red-200 font-semibold">Organizations &amp; Content Remain:</strong> Your organization memberships are detached upon deletion. Organizations, live streams, recordings, podcasts, and digital library content are <em>not</em> deleted and remain intact.
                </p>
                <p>
                    <strong class="text-red-900 dark:text-red-200 font-semibold">Ownership Transfer:</strong> Ownership does not transfer automatically. If you are the sole owner of an organization, the organization and its recordings remain preserved and unowned until a platform administrator reassigns ownership. If other owners or managers exist, they will continue managing the organization uninterrupted.
                </p>
                <p>
                    <strong class="text-red-900 dark:text-red-200 font-semibold">Organization Admins &amp; Members:</strong> Your membership is detached, leaving the organization and its leadership unaffected.
                </p>
                <p>
                    <strong class="text-red-900 dark:text-red-200 font-semibold">Platform Super Admins:</strong> Account deletion is blocked if you are the last platform administrator.
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
                <label for="delete_confirm" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                    Type <strong class="text-red-600 dark:text-red-400 font-bold uppercase">DELETE</strong> to confirm
                </label>
                <input
                    id="delete_confirm"
                    name="confirm"
                    type="text"
                    required
                    autocomplete="off"
                    placeholder="Type DELETE here..."
                    class="mt-1.5 w-full rounded-lg border border-red-300 dark:border-red-900/60 bg-white dark:bg-zinc-900 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-600 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                >
                @error('confirm')
                    <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="delete_password" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                    Confirm Your Password
                </label>
                <input
                    id="delete_password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter current password..."
                    class="mt-1.5 w-full rounded-lg border border-red-300 dark:border-red-900/60 bg-white dark:bg-zinc-900 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-600 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                >
                @error('password')
                    <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror
            </div>

            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 dark:border-red-800 bg-red-600 dark:bg-red-950 px-4 py-2.5 text-xs font-bold text-white dark:text-red-300 hover:bg-red-700 dark:hover:bg-red-900/80 transition focus:outline-none focus:ring-2 focus:ring-red-500"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                <span>Delete my account</span>
            </button>
        </form>
    </div>
</div>

<script>
    function toggleAuthPassword(btn) {
        if (!btn) return;
        var wrap = btn.closest('.auth-input-password-wrap');
        if (!wrap) return;
        var input = wrap.querySelector('input');
        if (!input) return;

        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        var newLabel = isPassword ? 'Hide password' : 'Show password';
        btn.setAttribute('aria-label', newLabel);
        btn.setAttribute('title', newLabel);

        var eyeOpen = btn.querySelector('.auth-eye-open');
        var eyeClosed = btn.querySelector('.auth-eye-closed');
        if (eyeOpen && eyeClosed) {
            if (isPassword) {
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
    }
</script>
@endsection
