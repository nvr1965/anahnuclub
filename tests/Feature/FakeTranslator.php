<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace Tests\Feature;

use App\Translation\Translator;

class FakeTranslator implements Translator
{
    public int $calls = 0;

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $this->calls++;

        return "[$targetLocale] $text";
    }

    public function name(): string
    {
        return 'fake';
    }
}
