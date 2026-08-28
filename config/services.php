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
        // confirmed to be the case on most of this network's Linux fleet,
        // not just the KUWIN Radius server this was first built for (root
        // SSH login is disabled; a regular account + `su -` is required
        // instead). Reuses the RADIUS_* variables since it's the same
        // shared account/policy across the network — see SshSuEscalation.
        'fallback_username' => env('RADIUS_SSH_USERNAME'),
        'fallback_password' => env('RADIUS_SSH_PASSWORD'),
        'su_password' => env('RADIUS_SU_PASSWORD'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'telegram_daily_report' => [
        'bot_token' => env('TELEGRAM_DAILY_REPORT_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_DAILY_REPORT_CHAT_ID'),
    ],

    // Shared secret the (not-yet-installed) server room temperature/
    // humidity sensor sends back on each reading it pushes — see
    // EnvironmentController::ingest(). Unset until a device exists, in
    // which case every ingest request is rejected.
    'environment_sensor' => [
        'token' => env('ENVIRONMENT_SENSOR_TOKEN'),
    ],

    // The KUWIN Radius page reads /var/log/radius/radius.log from this host
    // over SSH. It's a dedicated box outside the regular VM fleet Smart
    // Detection/ModSecurity SSH into, so it very likely needs its own
    // login rather than the shared guest_ssh credential above — set
    // RADIUS_SSH_USERNAME/PASSWORD to override; left blank, it falls back
    // to GUEST_SSH_USERNAME/PASSWORD in case they do happen to work here.
    'radius' => [
        'host' => env('RADIUS_SERVER_HOST', '158.108.96.18'),
        'ssh_username' => env('RADIUS_SSH_USERNAME'),
        'ssh_password' => env('RADIUS_SSH_PASSWORD'),
        'ssh_port' => env('RADIUS_SSH_PORT', 22),
        // The SSH login above (a regular user) can't read radius.log
        // directly — the same "su -" root password used manually is
        // needed to escalate over an interactive shell. Left blank, the
        // page fails with a clear message instead of silently trying.
        'su_password' => env('RADIUS_SU_PASSWORD'),
    ],

];
