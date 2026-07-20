<div class="stage-player stage-rise-delay-2">
    <div id="stage-wave" class="stage-wave" aria-hidden="true"></div>
    <div class="stage-transport">
        <button type="button" id="btn-volume" class="stage-transport-btn" aria-label="Mute" title="Volume">
            <svg class="icon-volume" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 10v4h4l5 4V6L7 10H3zm13.5 2a3.5 3.5 0 0 0-1.8-3.1v6.2A3.5 3.5 0 0 0 16.5 12zM14 4.7v2.1a5.5 5.5 0 0 1 0 10.4v2.1a7.5 7.5 0 0 0 0-14.6z"/></svg>
            <svg class="icon-muted hidden" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.5 12a3.5 3.5 0 0 0-1.8-3.1v2.36l1.75 1.75c.03-.33.05-.67.05-1.01zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51A8.8 8.8 0 0 0 21 12c0-3.53-2.04-6.55-5-7.97v2.21A5.5 5.5 0 0 1 19 12zM4.27 3 3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.14a8.94 8.94 0 0 0 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4 9.91 6.09 12 8.18V4z"/></svg>
        </button>
        <div id="stage-play-shell" class="player-breath rounded-full">
            <button type="button" id="btn-play" class="stage-play" aria-label="Play" @if(! empty($disabled)) disabled @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/></svg>
            </button>
        </div>
        <button type="button" id="btn-wave-toggle" class="stage-transport-btn is-on" aria-label="Toggle visualizer" aria-pressed="true" title="Visualizer">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 10h2v8H4v-8zm5-6h2v14H9V4zm5 3h2v11h-2V7zm5 4h2v7h-2v-7z"/></svg>
        </button>
    </div>
    <audio id="stream-audio" class="sr-only" playsinline></audio>
    <p id="stream-status" class="stage-status-line">{{ $status ?? 'Connecting…' }}</p>
</div>
