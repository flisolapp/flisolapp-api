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

    // ── Mail transport driver ─────────────────────────────────────────────────
    // "mailersend"  → MailerSendApiTransport  (HTTPS, no SMTP — use on A2Hosting)
    // "smtp"        → SmtpMailTransport        (Laravel Mail facade, local dev)
    'mail_transport' => env('MAIL_TRANSPORT', 'smtp'),

    // ── MailerSend ────────────────────────────────────────────────────────────
    // Token: https://app.mailersend.com/api-tokens
    // The sending domain must be verified in MailerSend.
    'mailersend' => [
        'token' => env('MAILERSEND_API_TOKEN'),
    ],

    // ── Mailgun (future) ──────────────────────────────────────────────────────
    // 'mailgun' => [
    //     'domain' => env('MAILGUN_DOMAIN'),
    //     'secret' => env('MAILGUN_SECRET'),
    // ],

    // ── Mandrill (future) ─────────────────────────────────────────────────────
    // 'mandrill' => [
    //     'secret' => env('MANDRILL_API_KEY'),
    // ],

];
