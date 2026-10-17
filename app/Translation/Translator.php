<?php

namespace App\Translation;

interface Translator
{
    /**
     * Translate plain text or simple HTML into the target language, keeping markup intact.
     */
    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string;

    public function name(): string;
}
