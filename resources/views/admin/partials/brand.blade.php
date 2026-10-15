@php
    $brandName = config('app.name', 'Sound Mix Live');
    $consoleContext = 'Operations Console';
@endphp
<a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 sm:gap-2.5 transition-opacity hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 rounded" aria-label="{{ $brandName }} {{ $consoleContext }}">
    <div class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-950/80 ring-1 ring-emerald-700/60 overflow-hidden shadow-inner">
        <img
            class="h-5 w-5 object-contain"
            src="{{ asset('brand/soundmix-mark.png') }}?v=20260723d"
            alt=""
            width="20"
            height="20"
            aria-hidden="true"
        >
    </div>
    <div class="flex flex-col min-w-0">
        <span class="text-xs sm:text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 flex items-center gap-1 sm:gap-1.5 truncate">
            <span>{{ $brandName }}</span>
            <span class="inline-flex items-center rounded px-1.5 py-0.2 text-[10px] font-medium tracking-wide bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800/60 uppercase">Console</span>
        </span>
        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-normal leading-tight hidden sm:block">Super Admin Platform</span>
    </div>
</a>
