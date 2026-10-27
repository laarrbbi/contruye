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

    'chargily' => [
        // false switches card payments off on purpose (a CCP-only launch).
        // Blank keys with this left on fail `app:check-secrets --strict`.
        'enabled' => (bool) env('CHARGILY_ENABLED', true),
        'api_key' => env('CHARGILY_API_KEY'),
        'secret' => env('CHARGILY_SECRET'),
        'base_url' => env('CHARGILY_BASE_URL', 'https://pay.chargily.net/test/api/v2'),
        'test_mode' => env('CHARGILY_TEST_MODE', true),
    ],

    'stripe' => [
        // false switches euro payments off on purpose. Blank is not off.
        'enabled' => (bool) env('STRIPE_ENABLED', true),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'bunny' => [
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'token_key' => env('BUNNY_TOKEN_KEY'),
        'embed_ttl' => (int) env('BUNNY_EMBED_TTL', 14400),
    ],

    'manual_payment' => [
        'ccp_number' => env('PAYMENT_CCP_NUMBER'),
        'ccp_name' => env('PAYMENT_CCP_NAME'),
        'baridimob_rip' => env('PAYMENT_BARIDIMOB_RIP'),
    ],

];
