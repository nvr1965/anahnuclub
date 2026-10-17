<?php

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
