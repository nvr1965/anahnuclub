<?php

namespace App\Translation;

use Illuminate\Support\Facades\Http;

class DeepLTranslator implements Translator
{
    public function __construct(private string $apiKey) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        // Free-plan keys end with ":fx" and use a separate host.
        $host = str_ends_with($this->apiKey, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';

        $response = Http::withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->apiKey])
            ->asForm()
            ->post($host.'/v2/translate', array_filter([
                'text' => $text,
                'target_lang' => strtoupper($targetLocale === 'en' ? 'en-gb' : $targetLocale),
                'source_lang' => $sourceLocale ? strtoupper($sourceLocale) : null,
                'tag_handling' => 'html',
            ]))
            ->throw();

        return $response->json('translations.0.text', $text);
    }

    public function name(): string
    {
        return 'deepl';
    }
}
