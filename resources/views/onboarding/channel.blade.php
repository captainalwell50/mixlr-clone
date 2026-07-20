@extends('layouts.app')

@section('title', 'Channel details')

@section('content')
    <div class="mx-auto max-w-md">
        <p class="site-section-label">{{ $creatorType->label() }}</p>
        <h1 class="console-title mt-2">Name your channel</h1>
        <p class="console-lead">This creates your public page and a default Studio stream.</p>

        <form method="POST" action="{{ route('onboarding.channel.store') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="name">Channel name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus maxlength="255">
            </div>
            <div>
                <label for="slug">Public URL slug <span class="text-[var(--stage-muted)]">(optional)</span></label>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-[var(--stage-muted)]">/c/</span>
                    <input id="slug" type="text" name="slug" value="{{ old('slug') }}" maxlength="255" placeholder="your-church">
                </div>
            </div>
            <div>
                <label for="tagline">Tagline <span class="text-[var(--stage-muted)]">(optional)</span></label>
                <input id="tagline" type="text" name="tagline" value="{{ old('tagline') }}" maxlength="255">
            </div>
            <div>
                <label for="theme_color">Accent color</label>
                <input id="theme_color" type="color" name="theme_color" value="{{ old('theme_color', '#3d9b7a') }}" class="h-10 w-20 cursor-pointer rounded border-0 bg-transparent p-0">
            </div>
            <button type="submit" class="console-btn console-btn-primary w-full">Create channel</button>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('onboarding.show') }}" class="console-link">Back</a>
        </p>
    </div>
@endsection
