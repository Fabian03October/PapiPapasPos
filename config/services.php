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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_wallet' => [
        'enabled' => env('GOOGLE_WALLET_ENABLED', false),
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
        'class_suffix' => env('GOOGLE_WALLET_CLASS_SUFFIX', 'papipapas_lealtad'),
        'service_account_json' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_JSON'),
        'program_name' => env('GOOGLE_WALLET_PROGRAM_NAME', "Papi's Papas"),
        'issuer_name' => env('GOOGLE_WALLET_ISSUER_NAME', "Papi's Papas"),
        'logo_url' => env('GOOGLE_WALLET_LOGO_URL'),
        'hex_background_color' => env('GOOGLE_WALLET_COLOR', '#2563eb'),
        'merchant_lat' => env('GOOGLE_WALLET_MERCHANT_LAT'),
        'merchant_lng' => env('GOOGLE_WALLET_MERCHANT_LNG'),
        'daily_broadcast_limit' => env('GOOGLE_WALLET_DAILY_BROADCAST_LIMIT', 1),
        'notify_on_visit_update' => env('GOOGLE_WALLET_NOTIFY_ON_VISIT_UPDATE', false),
    ],

];
