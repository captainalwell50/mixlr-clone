<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
        'scopes' => ['openid', 'profile', 'email'],
    ],

    'google_drive' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_DRIVE_REDIRECT_URI', env('APP_URL').'/integrations/google-drive/callback'),
        'scopes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'GOOGLE_DRIVE_SCOPES',
                'https://www.googleapis.com/auth/drive.file,https://www.googleapis.com/auth/userinfo.email'
            ))
        ))),
    ],

    'cloudflare' => [
        'email_api_token' => env('CLOUDFLARE_EMAIL_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'domain' => env('CLOUDFLARE_EMAIL_DOMAIN', 'soundmix.live'),
        'default_destination' => env('CLOUDFLARE_DEFAULT_DESTINATION'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_SUPPORT_ENABLED', true),
        'number' => env('WHATSAPP_SUPPORT_NUMBER'),
        'default_message' => env('WHATSAPP_DEFAULT_MESSAGE', "Hello SoundMix Support,\nI need help with SoundMix."),
    ],

];
