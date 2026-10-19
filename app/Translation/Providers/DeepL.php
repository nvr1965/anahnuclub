<?php

namespace App\Translation\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** DeepL: very good for European languages; the free plan gives 500,000 characters a month. */
class DeepL extends Provider
{
    public function __construct(private string $apiKey) {}

    /** DeepL wants a regional variant for a few target languages. */
    private const TARGETS = ['en' => 'EN-GB', 'pt' => 'PT-PT', 'zh' => 'ZH-HANS'];

    /**
     * Several texts in one request (up to 50).
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $response = $this->http()
            ->asJson()
            ->post($this->host().'/v2/translate', array_filter([
                'text' => array_values($texts),
                'target_lang' => self::TARGETS[$targetLocale] ?? strtoupper($targetLocale),
                'source_lang' => $sourceLocale ? strtoupper($sourceLocale) : null,
                'tag_handling' => 'html',
            ]))
            ->throw();

        return array_map(fn ($t) => $t['text'], $response->json('translations', [])) ?: $texts;
    }

    /**
     * Whether DeepL translates into this language, from its own list (asked once a day).
     * If DeepL cannot be reached, the answer is yes so a short outage does not block anything.
     */
    public function supports(string $locale): bool
    {
        try {
            $targets = Cache::remember('deepl.targets', now()->addDay(), fn () => array_map(
                fn ($l) => strtolower(explode('-', $l['language'])[0]),
                $this->http()->get($this->host().'/v2/languages', ['type' => 'target'])->throw()->json(),
            ));
        } catch (Throwable) {
            return true;
        }

        return in_array(strtolower($locale), $targets, true);
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->apiKey]);
    }

    /** Free-plan keys end with ":fx" and use a separate host. */
    private function host(): string
    {
        return str_ends_with($this->apiKey, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
    }

    public function name(): string
    {
        return 'deepl';
    }
}
