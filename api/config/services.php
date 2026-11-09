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

    // The financial template service (financials/ at the root of the
    // repository): Node.js, reachable only from the API.
    'plantilla' => [
        'url' => env('PLANTILLA_URL', 'http://127.0.0.1:3100'),
        'timeout' => (int) env('PLANTILLA_TIMEOUT', 60),
        // 'http': the financials service (financials/server.js). 'process': one
        // `node financials/cli.js` per calculation, where no service can stay up
        // (the Vercel preview).
        'transport' => env('PLANTILLA_TRANSPORT', 'http'),
        'node' => env('PLANTILLA_NODE', 'node'),
        'cli' => env('PLANTILLA_CLI', base_path('../financials/cli.js')),
    ],

    'stripe' => [
        // false switches card payments off on purpose. Blank is not off.
        'enabled' => (bool) env('STRIPE_ENABLED', true),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // A lesson video served as a file from this base («/video/» + id + «.mp4»), when
    // Bunny Stream is not configured: the Vercel preview, before the films are on Bunny.
    'video_files' => [
        'base' => env('VIDEO_FILES_BASE'),
    ],

    'bunny' => [
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'token_key' => env('BUNNY_TOKEN_KEY'),
        'embed_ttl' => (int) env('BUNNY_EMBED_TTL', 14400),
    ],

    'manual_payment' => [
        // Bank transfer: the account the buyer sends the money to.
        'iban' => env('PAYMENT_TRANSFER_IBAN'),
        'holder' => env('PAYMENT_TRANSFER_HOLDER'),
        // Bizum: the mobile number registered for Bizum on that account.
        'bizum_phone' => env('PAYMENT_BIZUM_PHONE'),
    ],

];
