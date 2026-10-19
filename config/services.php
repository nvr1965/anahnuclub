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

    'translation' => [
        // Old single choice (null | claude | gemini | deepl ...); if set, that service is tried first. See config/translation.php.
        'driver' => env('TRANSLATION_DRIVER', 'null'),
        // Translate while the reader waits (slow); off = only show translations prepared in the background.
        'on_demand' => (bool) env('TRANSLATION_ON_DEMAND', false),
        // Characters the nightly translations:fill may send to the provider; 0 = no limit.
        // DeepL API Free allows 500,000 a month, so about 15000 a day keeps within it.
        'daily_chars' => (int) env('TRANSLATION_DAILY_CHARS', 0),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'translation_model' => env('ANTHROPIC_TRANSLATION_MODEL', 'claude-haiku-5-5'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'translation_model' => env('GEMINI_TRANSLATION_MODEL', 'gemini-flash-lite-latest'),
    ],

    'google_translate' => [
        'key' => env('GOOGLE_TRANSLATE_KEY'),
    ],

    'libretranslate' => [
        'url' => env('LIBRETRANSLATE_URL'),
        'key' => env('LIBRETRANSLATE_KEY'),
    ],

    'mymemory' => [
        // An address raises the free amount from 5,000 to 50,000 characters a day.
        'email' => env('MYMEMORY_EMAIL'),
    ],

    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
    ],

];
