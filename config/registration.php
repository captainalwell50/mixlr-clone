<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Soft email verification on self-serve signup
    |--------------------------------------------------------------------------
    |
    | Default false — do not enable on production until MAIL_MAILER is a real
    | transport (not log/array) and verification routes are wired. Operator-
    | created users are always marked verified and are unaffected.
    |
    */

    'require_verification' => (bool) env('REGISTRATION_REQUIRE_VERIFICATION', false),

    /*
    |--------------------------------------------------------------------------
    | Anti-bot: minimum seconds between form render and submit
    |--------------------------------------------------------------------------
    */

    'min_form_seconds' => max(0, (int) env('REGISTRATION_MIN_SECONDS', 3)),

    /*
    |--------------------------------------------------------------------------
    | Honeypot field name (must stay empty; hidden on the register form)
    |--------------------------------------------------------------------------
    */

    'honeypot' => 'website',

    /*
    |--------------------------------------------------------------------------
    | Block common disposable email domains on public register
    |--------------------------------------------------------------------------
    */

    'block_disposable_emails' => (bool) env('REGISTRATION_BLOCK_DISPOSABLE', true),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile (optional)
    |--------------------------------------------------------------------------
    |
    | Both keys must be set for the widget + server check to activate.
    | When unset, honeypot + throttle + name rules still protect signup.
    |
    */

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
