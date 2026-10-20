<?php

/*
 * The translation block: machine-translation services tried in turn. The keys live in config/services.php
 * (and .env); the order, on/off and daily limits can be changed on the admin page "Translators".
 */
return [
    // Order before an administrator sets one: comma-separated ids; empty means none (tests).
    'order' => env('TRANSLATION_PROVIDERS') === null
        ? ['deepl', 'microsoft', 'google', 'gemini', 'libretranslate', 'mymemory', 'claude']
        : array_values(array_filter(array_map('trim', explode(',', (string) env('TRANSLATION_PROVIDERS'))))),

    /*
     * price: free (no money ever), free_tier (free up to a monthly amount, then paid), paid.
     * needs: the config value that must be set for the service to work, with the .env name to show.
     * daily_chars: starting daily limit (0 = none), so a free amount is not overrun.
     */
    'providers' => [
        'deepl' => ['label' => 'DeepL', 'price' => 'free_tier', 'needs' => ['services.deepl.key' => 'DEEPL_API_KEY'], 'daily_chars' => 15000,
            'note' => '500,000 characters a month free'],
        'microsoft' => ['label' => 'Microsoft Translator', 'price' => 'free_tier', 'needs' => ['services.microsoft_translator.key' => 'MICROSOFT_TRANSLATOR_KEY'], 'daily_chars' => 60000,
            'note' => '2,000,000 characters a month free'],
        'google' => ['label' => 'Google Translate', 'price' => 'free_tier', 'needs' => ['services.google_translate.key' => 'GOOGLE_TRANSLATE_KEY'], 'daily_chars' => 15000,
            'note' => '500,000 characters a month free'],
        'gemini' => ['label' => 'Gemini', 'price' => 'free_tier', 'needs' => ['services.gemini.key' => 'GEMINI_API_KEY'], 'daily_chars' => 0,
            'note' => 'Free tier with request limits'],
        'libretranslate' => ['label' => 'LibreTranslate', 'price' => 'free', 'needs' => ['services.libretranslate.url' => 'LIBRETRANSLATE_URL'], 'daily_chars' => 0,
            'note' => 'Open source, on your own server'],
        'mymemory' => ['label' => 'MyMemory', 'price' => 'free', 'needs' => [], 'daily_chars' => env('MYMEMORY_EMAIL') ? 45000 : 4500,
            'note' => '5,000 characters a day, 50,000 with MYMEMORY_EMAIL'],
        'claude' => ['label' => 'Claude', 'price' => 'paid', 'needs' => ['services.anthropic.key' => 'ANTHROPIC_API_KEY'], 'daily_chars' => 0,
            'note' => 'Any language, paid per use'],
    ],
];
