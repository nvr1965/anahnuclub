<?php

namespace App\Translation\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Microsoft Translator (Azure AI Translator): over 100 languages; the free F0 tier gives 2,000,000 characters a month. */
class Microsoft extends Provider
{
    public function __construct(private string $key, private ?string $region = null) {}

    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $out = [];
        // One request takes up to 1,000 texts and 50,000 characters; smaller pieces keep well inside that.
        foreach (array_chunk(array_values($texts), 50) as $chunk) {
            $response = Http::timeout(30)
                ->withHeaders(array_filter(['Ocp-Apim-Subscription-Key' => $this->key, 'Ocp-Apim-Subscription-Region' => $this->region]))
                ->post('https://api.cognitive.microsofttranslator.com/translate?'.http_build_query(array_filter([
                    'api-version' => '3.0', 'to' => $targetLocale, 'from' => $sourceLocale, 'textType' => 'html',
                ])), array_map(fn ($t) => ['Text' => $t], $chunk))
                ->throw();
            $done = $response->json();
            if (! is_array($done) || count($done) !== count($chunk)) {
                throw new RuntimeException('Microsoft Translator returned an unexpected answer');
            }
            foreach ($done as $item) {
                $out[] = (string) ($item['translations'][0]['text'] ?? '');
            }
        }

        return $out;
    }

    public function name(): string
    {
        return 'microsoft';
    }
}
