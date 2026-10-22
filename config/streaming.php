<?php

return [

    /*
     | Public listen policy (Studio / creator preview stays on WHEP).
     |
     | prefer_hls: null = auto (true when CDN base is set OR AAC sidecar is on),
     |             true/false = explicit override via LISTEN_PREFER_HLS.
     | Admin UI (/admin/settings) can override this via site_settings.listen_prefer_hls;
     | when that row is set it wins over this .env default.
     | hls_aac_sidecar: point public HLS URLs at MediaMTX live/<uuid>/aac
     |   (ffmpeg Opus→AAC remux). Requires deploy/mediamtx AAC path + hook.
     */
    'listen' => [
        'prefer_hls' => (($v = env('LISTEN_PREFER_HLS')) === null || $v === '')
            ? null
            : filter_var($v, FILTER_VALIDATE_BOOLEAN),
        'hls_aac_sidecar' => filter_var(env('LISTEN_HLS_AAC_SIDECAR', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'mediamtx' => [
        'webrtc_public_base' => env('MEDIAMTX_WEBRTC_PUBLIC_BASE', 'http://127.0.0.1:8889'),
        'hls_public_base' => env('MEDIAMTX_HLS_PUBLIC_BASE', 'http://127.0.0.1:8888'),

        /*
         * Optional CDN / Front Door origin in front of HLS (for ~1k listeners).
         * When set, Listen/embed playlist URLs use this base instead of hls_public_base.
         * Example: https://cdn.example.org/hls
         */
        'hls_cdn_base' => env('MEDIAMTX_HLS_CDN_BASE'),

        /*
         * Public RTMP base for OBS (no path). Example: rtmp://stream.example.org:1935
         */
        'rtmp_public_base' => env('MEDIAMTX_RTMP_PUBLIC_BASE', 'rtmp://127.0.0.1:1935'),

        'webhook_secret' => env('MEDIAMTX_WEBHOOK_SECRET'),

        /*
         * Optional global publish secret (in addition to per-stream stream_key).
         */
        'publish_secret' => env('MEDIAMTX_PUBLISH_SECRET'),

        /*
         * Wait this long after publisher not_ready before marking the stream offline /
         * pausing the live event. Covers browser refresh and brief WHIP reconnects.
         * Set 0 in tests for immediate finalize.
         */
        'publisher_disconnect_grace_seconds' => (int) env('MEDIAMTX_PUBLISHER_DISCONNECT_GRACE_SECONDS', 45),

        /*
         * Short race window: MediaMTX publisher replace can emit not_ready then ready
         * out of order on sync PHP-FPM.
         */
        'not_ready_race_ms' => (int) env('MEDIAMTX_NOT_READY_RACE_MS', 500),

        'recording_retention_days' => (int) env('RECORDING_RETENTION_DAYS', 365),

        /*
         * Ignore MediaMTX reconnect blips shorter than this (the file is deleted).
         * Longer parts stay private until the event ends, then they are stitched
         * into one podcast and the parts are deleted.
         */
        'archive_min_segment_seconds' => (int) env('MEDIAMTX_ARCHIVE_MIN_SEGMENT_SECONDS', 5),

        /*
         * Fallback when duration_raw is missing: require at least this many bytes.
         */
        'archive_min_segment_bytes' => (int) env('MEDIAMTX_ARCHIVE_MIN_SEGMENT_BYTES', 80_000),

        /*
         * Usable reconnect parts must be at least this large / this bitrate.
         * Thin files with inflated duration metadata are discarded.
         */
        'assemble_min_usable_bytes' => (int) env('MEDIAMTX_ASSEMBLE_MIN_USABLE_BYTES', 400_000),
        'assemble_min_bitrate_bps' => (int) env('MEDIAMTX_ASSEMBLE_MIN_BITRATE_BPS', 16_000),

        /*
         * Untracked files (reconnect blips never inserted into recordings) newer
         * than this are left on disk so an in-progress MediaMTX segment is not deleted.
         */
        'untracked_grace_minutes' => (int) env('MEDIAMTX_UNTRACKED_GRACE_MINUTES', 20),

        /*
         * Do not catalog a MediaMTX file this soon after it was last written.
         * The recorder appends to one fMP4 for the whole broadcast; the row is
         * created only when the segment closes.
         */
        'adopt_settle_seconds' => (int) env('MEDIAMTX_ADOPT_SETTLE_SECONDS', 120),
    ],

    /*
     | ffmpeg used to stitch MediaMTX fmp4 parts at end-live. Empty = auto-detect.
     */
    'ffmpeg_path' => env('FFMPEG_PATH'),

    /*
     | AAC bitrate for the public podcast (live WHIP stays high-quality Opus).
     | 128 kbps ≈ 56 MB/hour. Live copy is ~510 kbps ≈ 230 MB/hour.
     | Public Listen HLS is a separate ffmpeg sidecar (LISTEN_HLS_AAC_BITRATE,
     | default 256k in docker/mediamtx/hooks/run-ready.sh) — not this archive value.
     */
    'archive_audio_bitrate_k' => (int) env('ARCHIVE_AUDIO_BITRATE_K', 128),

    /*
     | Auto-end live/paused events after the publisher has been gone this long.
     | Prevents paused shows from pinning the origin VM on the peak SKU.
     | 0 disables. Does not end events whose stream is still Live.
     */
    'idle_event_auto_end_minutes' => (int) env('IDLE_EVENT_AUTO_END_MINUTES', 60),

    'mediamtx_recordings_path' => env('MEDIAMTX_RECORDINGS_PATH', 'mediamtx-recordings'),

];
