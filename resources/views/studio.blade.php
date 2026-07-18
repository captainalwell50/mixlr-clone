@extends('layouts.stream')

@section('title', 'Studio · '.$stream->title)

@section('vite')
    @vite(['resources/js/studio.js'])
@endsection

@section('content')
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-amber-400/90">Broadcaster</p>
        <h1 class="mt-1 text-2xl font-semibold text-white">{{ $stream->title }}</h1>
        <p class="mt-1 text-sm text-zinc-400">{{ $organization->name }}</p>
    </div>

    <div class="flex flex-col gap-3 rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
        <label class="block text-sm font-medium text-zinc-300" for="audio-input">Microphone / interface</label>
        <select id="audio-input" class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"></select>

        <div>
            <div class="mb-1 flex items-center justify-between text-xs text-zinc-500">
                <span>Input level</span>
                <span id="meter-label">—</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-zinc-800">
                <div id="level-meter" class="h-full w-0 rounded-full bg-emerald-500 transition-[width] duration-75"></div>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="button" id="btn-start"
                class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">
                Go live
            </button>
            <button type="button" id="btn-stop"
                class="flex-1 rounded-lg bg-zinc-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-600 disabled:opacity-40"
                disabled>
                Stop
            </button>
        </div>

        <p id="studio-status" class="text-sm text-zinc-400">Allow microphone access when prompted, then press Go live.</p>
    </div>

    <div
        id="studio-root"
        data-whip-url="{{ $whipUrl }}"
        class="hidden"
    ></div>
@endsection
