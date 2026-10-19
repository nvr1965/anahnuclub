<?php

namespace App\Translation\Providers;

use App\Translation\Translator;

/**
 * One machine-translation service. Providers are lined up in a TranslatorChain, which picks the first
 * one that is set up, knows the language and is within its daily limit, and moves on when one fails.
 */
abstract class Provider implements Translator
{
    /**
     * Several texts at once, in the same order. Providers without a batch call translate one by one.
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        return array_map(fn ($t) => $this->translate($t, $targetLocale, $sourceLocale), array_values($texts));
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return $this->translateMany([$text], $targetLocale, $sourceLocale)[0] ?? $text;
    }

    /** Whether the service translates into this language. */
    public function supports(string $locale): bool
    {
        return true;
    }
}
