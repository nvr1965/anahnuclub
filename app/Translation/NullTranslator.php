<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Translation;

/** Used when no translation provider is configured: content is shown as written. */
class NullTranslator implements Translator
{
    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return $text;
    }

    public function name(): string
    {
        return 'null';
    }
}
