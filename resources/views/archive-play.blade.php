@extends('layouts.stream')

@section('title', $stream->title.' · Archive')

@section('vite')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-emerald-400/90">Recording</p>
        <h1 class="mt-1 text-2xl font-semibold text-white">{{ $stream->title }}</h1>
        <p class="mt-1 text-sm text-zinc-400">
            {{ $stream->organization->name }}
            ·
            {{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}
        </p>
    </div>

    <audio class="w-full rounded-lg" controls playsinline src="{{ $fileUrl }}"></audio>

    <p class="text-sm">
        <a href="{{ route('archive.index') }}" class="text-zinc-400 hover:text-white">← Back to archive</a>
    </p>
@endsection
