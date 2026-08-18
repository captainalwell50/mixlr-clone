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
         * Ignore MediaMTX segments shorter than this (reconnect blips). Longer
         * segments become public podcasts when the linked event has ended.
         */
        'archive_min_segment_seconds' => (int) env('MEDIAMTX_ARCHIVE_MIN_SEGMENT_SECONDS', 60),

        /*
         * Fallback when duration_raw is missing: require at least this many bytes.
         */
        'archive_min_segment_bytes' => (int) env('MEDIAMTX_ARCHIVE_MIN_SEGMENT_BYTES', 80_000),
    ],

    'mediamtx_recordings_path' => env('MEDIAMTX_RECORDINGS_PATH', 'mediamtx-recordings'),

];
