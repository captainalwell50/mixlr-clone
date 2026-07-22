<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform object storage (R2 / S3 / GCS-via-S3)
    |--------------------------------------------------------------------------
    |
    | Used for live recordings archive and optional platform-hosted studio audio.
    | Leave OBJECT_STORAGE_ENABLED=false to keep everything on the VM disk.
    |
    */

    'enabled' => (bool) env('OBJECT_STORAGE_ENABLED', false),

    'disk' => env('OBJECT_STORAGE_DISK', 's3'),

    /*
     * After a successful upload, delete the local MediaMTX file to free VM disk.
     * Keep false until you've verified playback from object storage.
     */
    'delete_local_after_sync' => (bool) env('OBJECT_STORAGE_DELETE_LOCAL_AFTER_SYNC', false),

    'signed_url_minutes' => (int) env('OBJECT_STORAGE_SIGNED_URL_MINUTES', 120),

    'prefix' => trim((string) env('OBJECT_STORAGE_PREFIX', 'live-mix'), '/'),

    /** Fallback org quota when no plan is attached. */
    'default_org_quota_bytes' => (int) env('OBJECT_STORAGE_DEFAULT_ORG_QUOTA_BYTES', 2 * 1024 * 1024 * 1024),

];
