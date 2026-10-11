@props([
    'mode' => 'compact', // 'compact' | 'expanded'
    'class' => '',
])

@if ($mode === 'expanded')
    <div class="space-y-3 {{ $class }}">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" role="radiogroup" aria-label="Theme preference">
            <!-- System Option -->
            <button
                type="button"
                data-theme-option="system"
                onclick="window.SoundMixTheme.setTheme('system')"
                class="group relative flex flex-col items-start p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 text-left transition hover:border-zinc-700 hover:bg-zinc-800/80 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                role="radio"
                aria-label="System theme"
            >
                <div class="flex items-center justify-between w-full mb-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-800 border border-zinc-700/80 text-zinc-300 group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
                        </svg>
                    </div>
                    <span class="theme-check-icon hidden h-4 w-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✓</span>
                </div>
                <div class="text-xs font-semibold text-white tracking-tight">System Automatic</div>
                <div class="text-[11px] text-zinc-400 mt-0.5 leading-snug">Syncs automatically with your operating system preference.</div>
            </button>

            <!-- Light Option -->
            <button
                type="button"
                data-theme-option="light"
                onclick="window.SoundMixTheme.setTheme('light')"
                class="group relative flex flex-col items-start p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 text-left transition hover:border-zinc-700 hover:bg-zinc-800/80 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                role="radio"
                aria-label="Light theme"
            >
                <div class="flex items-center justify-between w-full mb-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-950/60 border border-amber-800/60 text-amber-300 group-hover:text-amber-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                    </div>
                    <span class="theme-check-icon hidden h-4 w-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✓</span>
                </div>
                <div class="text-xs font-semibold text-white tracking-tight">Light Theme</div>
                <div class="text-[11px] text-zinc-400 mt-0.5 leading-snug">Crisp, structured daylight workspace with high-contrast surfaces.</div>
            </button>

            <!-- Dark Option -->
            <button
                type="button"
                data-theme-option="dark"
                onclick="window.SoundMixTheme.setTheme('dark')"
                class="group relative flex flex-col items-start p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 text-left transition hover:border-zinc-700 hover:bg-zinc-800/80 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                role="radio"
                aria-label="Dark theme"
            >
                <div class="flex items-center justify-between w-full mb-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-950/60 border border-indigo-800/60 text-indigo-300 group-hover:text-indigo-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </div>
                    <span class="theme-check-icon hidden h-4 w-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✓</span>
                </div>
                <div class="text-xs font-semibold text-white tracking-tight">Dark Theme</div>
                <div class="text-[11px] text-zinc-400 mt-0.5 leading-snug">High-contrast cinema console optimized for low-light broadcast environments.</div>
            </button>
        </div>
    </div>
@else
    <!-- Compact segmented selector -->
    <div class="theme-selector-compact inline-flex items-center rounded-lg border border-zinc-800 bg-zinc-900/80 p-0.5 shadow-sm {{ $class }}" role="group" aria-label="Theme selector">
        <button
            type="button"
            data-theme-option="light"
            onclick="window.SoundMixTheme.setTheme('light')"
            class="theme-option-btn inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-zinc-400 hover:text-zinc-200 transition focus:outline-none focus:ring-1 focus:ring-emerald-500"
            title="Light theme"
            aria-label="Switch to light theme"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <span class="hidden sm:inline text-[11px]">Light</span>
        </button>

        <button
            type="button"
            data-theme-option="dark"
            onclick="window.SoundMixTheme.setTheme('dark')"
            class="theme-option-btn inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-zinc-400 hover:text-zinc-200 transition focus:outline-none focus:ring-1 focus:ring-emerald-500"
            title="Dark theme"
            aria-label="Switch to dark theme"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
            <span class="hidden sm:inline text-[11px]">Dark</span>
        </button>

        <button
            type="button"
            data-theme-option="system"
            onclick="window.SoundMixTheme.setTheme('system')"
            class="theme-option-btn inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-zinc-400 hover:text-zinc-200 transition focus:outline-none focus:ring-1 focus:ring-emerald-500"
            title="System theme"
            aria-label="Switch to system theme"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
            </svg>
            <span class="hidden sm:inline text-[11px]">Auto</span>
        </button>
    </div>
@endif
