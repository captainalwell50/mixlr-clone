@php
    $href = $href ?? '#';
    $title = $title ?? '';
    $subtitle = $subtitle ?? null;
    $artwork = $artwork ?? null;
    $accent = $accent ?? '#3d9b7a';
    $live = $live ?? false;
@endphp
<a href="{{ $href }}" class="art-tile" style="--tile-accent: {{ $accent }};">
    <div
        class="art-tile-frame {{ $artwork ? 'has-art' : '' }}"
        @if ($artwork) style="--tile-art: url('{{ $artwork }}')" @endif
    >
        @if ($live)
            <span class="art-tile-live">
                <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                Live
            </span>
        @endif
    </div>
    <div class="art-tile-meta">
        <h3>{{ $title }}</h3>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
</a>
