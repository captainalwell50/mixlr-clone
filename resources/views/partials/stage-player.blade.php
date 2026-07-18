<div class="stage-player stage-rise-delay-2">
    <div id="stage-wave" class="stage-wave" aria-hidden="true"></div>
    <div id="stage-play-shell" class="player-breath rounded-full">
        <button type="button" id="btn-play" class="stage-play" aria-label="Play" @if(! empty($disabled)) disabled @endif>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/></svg>
        </button>
    </div>
    <audio id="stream-audio" class="sr-only" playsinline></audio>
    <p id="stream-status" class="stage-status-line">{{ $status ?? 'Connecting…' }}</p>
</div>
