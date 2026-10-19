<?php

namespace App\Translation\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Cloud Translation: over 100 languages; the first 500,000 characters a month are free. */
class Google extends Provider
{
    public function __construct(private string $apiKey) {}

    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $out = [];
        foreach (array_chunk(array_values($texts), 100) as $chunk) {
            // Several texts are several "q" fields, which a plain form array cannot express.
            $body = http_build_query(array_filter(['target' => $targetLocale, 'source' => $sourceLocale, 'format' => 'html']))
                .'&'.implode('&', array_map(fn ($t) => 'q='.rawurlencode($t), $chunk));
            $response = Http::timeout(30)
                ->withBody($body, 'application/x-www-form-urlencoded')
                ->post('https://translation.googleapis.com/language/translate/v2?key='.rawurlencode($this->apiKey))
                ->throw();
            $done = $response->json('data.translations');
            if (! is_array($done) || count($done) !== count($chunk)) {
                throw new RuntimeException('Google returned an unexpected answer');
            }
            foreach ($done as $item) {
                $out[] = (string) $item['translatedText'];
            }
        }

        return $out;
    }

    public function name(): string
    {
        return 'google';
    }
}
