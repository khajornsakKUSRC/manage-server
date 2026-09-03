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
        // Fallback for hosts where direct root login above is rejected —
        // confirmed to be the case on most of this network's Linux fleet
        // (root SSH login is disabled; a regular account + `su -` is
        // required instead). One shared account/policy across the
        // network — see SshSuEscalation.
        'fallback_username' => env('SSH_FALLBACK_USERNAME'),
        'fallback_password' => env('SSH_FALLBACK_PASSWORD'),
        'su_password' => env('SSH_FALLBACK_SU_PASSWORD'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'telegram_daily_report' => [
        'bot_token' => env('TELEGRAM_DAILY_REPORT_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_DAILY_REPORT_CHAT_ID'),
    ],

    // Its own bot/chat so "new repair request" pings don't mix with the
    // infra alarm channel. Leave unset to disable — see ItRepairNotificationService.
    'telegram_it_repair' => [
        'bot_token' => env('TELEGRAM_IT_REPAIR_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_IT_REPAIR_BOT_CHAT_ID'),
    ],

    // Shared secret the (not-yet-installed) server room temperature/
    // humidity sensor sends back on each reading it pushes — see
    // EnvironmentController::ingest(). Unset until a device exists, in
    // which case every ingest request is rejected.
    'environment_sensor' => [
        'token' => env('ENVIRONMENT_SENSOR_TOKEN'),
    ],

];
