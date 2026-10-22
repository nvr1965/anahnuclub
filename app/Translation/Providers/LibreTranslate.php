<?php

namespace App\Translation\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * LibreTranslate: open source, can run on one's own server for free (or a hosted one with a key).
 * Nothing leaves the server when it is one's own.
 */
class LibreTranslate extends Provider
{
    public function __construct(private string $url, private ?string $apiKey = null)
    {
        // An address typed in as "libretranslate.com" would fail every request; it means https.
        $this->url = preg_match('#^https?://#i', trim($url)) ? trim($url) : 'https://'.ltrim(trim($url), '/');
    }

    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $response = Http::timeout(60)->asJson()->post(rtrim($this->url, '/').'/translate', array_filter([
            'q' => array_values($texts),
            'source' => $sourceLocale ?: 'auto',
            'target' => $targetLocale,
            'format' => 'html',
            'api_key' => $this->apiKey,
        ]))->throw();

        $done = $response->json('translatedText');
        if (! is_array($done) || count($done) !== count($texts)) {
            throw new RuntimeException('LibreTranslate returned an unexpected answer');
        }

        return array_map('strval', $done);
    }

    public function supports(string $locale): bool
    {
        try {
            $codes = Cache::remember('libretranslate.languages', now()->addDay(), fn () => array_column(
                Http::timeout(10)->get(rtrim($this->url, '/').'/languages')->throw()->json(),
                'code',
            ));
        } catch (Throwable) {
            return true;
        }

        return in_array($locale, $codes, true);
    }

    public function name(): string
    {
        return 'libretranslate';
    }
}
