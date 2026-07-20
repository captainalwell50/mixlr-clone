@forelse ($galleryImages as $image)
    <button
        type="button"
        class="portal-gallery-item {{ $image->isVideo() ? 'is-video' : '' }}"
        data-id="{{ $image->id }}"
        data-url="{{ $image->url() }}"
        data-caption="{{ $image->caption }}"
        data-type="{{ $image->isVideo() ? 'video' : 'image' }}"
        @if ($image->duration_seconds) data-duration="{{ $image->duration_seconds }}" @endif
        @if ($image->posterUrl()) data-poster="{{ $image->posterUrl() }}" @endif
        aria-label="{{ $image->isVideo() ? 'Open video reel' : 'Open gallery photo' }}"
    >
        @if ($image->isVideo())
            <video src="{{ $image->url() }}" @if ($image->posterUrl()) poster="{{ $image->posterUrl() }}" @endif muted playsinline preload="metadata"></video>
            <span class="portal-gallery-reel-badge" aria-hidden="true">Reel</span>
            @if ($image->duration_seconds)
                <span class="portal-gallery-duration">{{ $image->duration_seconds }}s</span>
            @endif
        @else
            <img src="{{ $image->url() }}" alt="{{ $image->caption ?: 'Service photo' }}" loading="lazy">
        @endif
        @if ($image->caption)
            <span class="portal-gallery-caption">{{ $image->caption }}</span>
        @endif
    </button>
@empty
    <p class="portal-empty" id="gallery-empty">{{ $emptyMessage ?? 'No photos or video reels yet — they’ll appear here when the studio posts them.' }}</p>
@endforelse
