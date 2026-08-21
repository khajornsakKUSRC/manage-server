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

    'vsphere' => [
        'url' => env('VSPHERE_URL'),
        'username' => env('VSPHERE_USERNAME'),
        'password' => env('VSPHERE_PASSWORD'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'guest_ssh' => [
        'username' => env('GUEST_SSH_USERNAME'),
        'password' => env('GUEST_SSH_PASSWORD'),
        'port' => env('GUEST_SSH_PORT', 22),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'telegram_daily_report' => [
        'bot_token' => env('TELEGRAM_DAILY_REPORT_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_DAILY_REPORT_CHAT_ID'),
    ],

];
