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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'fingerspot' => [
        'url' => env('FINGERSPOT_API_URL', 'http://192.168.11.24'),
        'key' => env('FINGERSPOT_API_KEY'),
        'device_ip' => env('FINGERSPOT_DEVICE_IP', '192.168.11.24'),
        'device_port' => env('FINGERSPOT_DEVICE_PORT', 80),
        'timeout' => env('FINGERSPOT_TIMEOUT', 30),
        'auto_sync_interval' => env('FINGERSPOT_AUTO_SYNC_INTERVAL', 60), // minutes
    ],

];
