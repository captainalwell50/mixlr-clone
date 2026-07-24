<?php

/**
 * Store / desktop download links.
 * Leave URL empty until published — badges show as coming soon.
 */
return [

    'android_apk' => [
        'label' => 'Android APK',
        'group' => 'mobile',
        'badge' => 'android_apk',
        'url' => env('DOWNLOAD_ANDROID_APK_URL'),
        'available' => filled(env('DOWNLOAD_ANDROID_APK_URL')),
    ],

    'play_store' => [
        'label' => 'Google Play',
        'group' => 'mobile',
        'badge' => 'play_store',
        'url' => env('DOWNLOAD_PLAY_STORE_URL'),
        'available' => filled(env('DOWNLOAD_PLAY_STORE_URL')),
    ],

    'app_store' => [
        'label' => 'App Store',
        'group' => 'mobile',
        'badge' => 'app_store',
        'url' => env('DOWNLOAD_APP_STORE_URL'),
        'available' => filled(env('DOWNLOAD_APP_STORE_URL')),
    ],

    'macos' => [
        'label' => 'Mac',
        'group' => 'desktop',
        'badge' => 'mac_app_store',
        'url' => env('DOWNLOAD_MACOS_URL'),
        'available' => filled(env('DOWNLOAD_MACOS_URL')),
    ],

    'windows' => [
        'label' => 'Windows',
        'group' => 'desktop',
        'badge' => 'microsoft_store',
        'url' => env('DOWNLOAD_WINDOWS_URL'),
        'available' => filled(env('DOWNLOAD_WINDOWS_URL')),
    ],

];
