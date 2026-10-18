<?php

namespace App\Translation;

use Illuminate\Support\Facades\Http;

class DeepLTranslator implements Translator
{
    public function __construct(private string $apiKey) {}

    /** DeepL wants a regional variant for a few target languages. */
    private const TARGETS = ['en' => 'EN-GB', 'pt' => 'PT-PT', 'zh' => 'ZH-HANS'];

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return $this->translateMany([$text], $targetLocale, $sourceLocale)[0] ?? $text;
    }

    /**
     * Several texts in one request (up to 50).
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        // Free-plan keys end with ":fx" and use a separate host.
        $host = str_ends_with($this->apiKey, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';

        $response = Http::withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->apiKey])
            ->asJson()
            ->post($host.'/v2/translate', array_filter([
                'text' => array_values($texts),
                'target_lang' => self::TARGETS[$targetLocale] ?? strtoupper($targetLocale),
                'source_lang' => $sourceLocale ? strtoupper($sourceLocale) : null,
                'tag_handling' => 'html',
            ]))
            ->throw();

        return array_map(fn ($t) => $t['text'], $response->json('translations', [])) ?: $texts;
    }

    public function name(): string
    {
        return 'deepl';
    }
}
