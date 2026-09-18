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

    'bird' => [
        'api_key' => env('BIRD_API_KEY'),
        'base_url' => env('BIRD_BASE_URL', 'https://eu1.platform.bird.com'),
        'sms_enabled' => env('BIRD_SMS_ENABLED', false),
        'sms_from' => env('BIRD_SMS_FROM', 'ANOTEH'),
        'sms_category' => env('BIRD_SMS_CATEGORY', 'transactional'),
        'whatsapp_enabled' => env('BIRD_WHATSAPP_ENABLED', false),
        'whatsapp_template_slug' => env('BIRD_WHATSAPP_TEMPLATE_SLUG'),
        'whatsapp_template_language' => env('BIRD_WHATSAPP_TEMPLATE_LANGUAGE', 'ru'),
    ],

    'notifications' => [
        'mail_enabled' => env('NOTIFICATIONS_MAIL_ENABLED', true),
    ],

];
