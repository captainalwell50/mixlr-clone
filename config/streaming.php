<?php

return [

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

        'recording_retention_days' => (int) env('RECORDING_RETENTION_DAYS', 365),
    ],

    'mediamtx_recordings_path' => env('MEDIAMTX_RECORDINGS_PATH', 'mediamtx-recordings'),

];
