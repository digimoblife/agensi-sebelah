<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Renders HTML templates to PNG (Chromium screenshot route). RENDERER=fake in tests.
    'renderer' => [
        'driver' => env('RENDERER', 'real'),
        'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
        'timeout' => 60,
    ],

    // ADR 0004. Bot token comes from Pengaturan > Kredensial, not from here.
    'telegram' => [
        'client' => env('TELEGRAM_CLIENT', 'real'),
        'poll_timeout' => 25,
        'approval_ttl_days' => 7,
        'invite_ttl_days' => 7,
    ],

    // F4.2. Instagram fetches media by public URL; a local-first install needs a tunnel to expose /media only.
    // Without it, every post becomes a ready-to-upload package (F9.5).
    'publish' => [
        'driver' => env('PUBLISHER', 'real'),
        'public_media_base' => env('PUBLIC_MEDIA_BASE_URL'),
        'default_time' => '19:00',
        'late_after_minutes' => 30,
        'media_url_ttl_minutes' => 120,
    ],

];
