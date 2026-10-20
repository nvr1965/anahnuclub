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

    // Where contact form messages are copied to, besides the admin inbox; empty = inbox only.
    'contact' => [
        'email' => env('CONTACT_EMAIL', 'team@anahnu.club'),
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

    // Signing in with other accounts (Laravel Socialite). A button shows only when its keys are set in .env.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/auth/google/callback',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/auth/facebook/callback',
    ],

    // Video calls. STUN is free and enough for most networks; a TURN relay (e.g. metered.ca) helps the rest.
    'webrtc' => [
        'stun' => env('WEBRTC_STUN', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'),
        'turn_urls' => env('TURN_URLS'),
        'turn_username' => env('TURN_USERNAME'),
        'turn_credential' => env('TURN_CREDENTIAL'),
    ],

];
