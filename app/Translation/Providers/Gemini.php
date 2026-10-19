<?php

namespace App\Translation\Providers;

use App\Models\Language;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Gemini: many languages DeepL lacks (Uzbek, Georgian, Persian, ...); the API has a free tier. */
class Gemini extends Provider
{
    public function __construct(private string $apiKey, private string $model) {}

    /**
     * Several texts in one request, as a JSON array in and out.
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $name = fn (string $code) => (Language::find($code)?->name ?? $code)." ($code)";
        $prompt = 'You translate texts of a community social network. Translate each item of the JSON array '
            .($sourceLocale ? 'from '.$name($sourceLocale).' ' : '').'into '.$name($targetLocale).'. '
            .'Keep the tone, names, emoji, links, HTML tags and placeholders such as :name exactly as they are. '
            .'Answer with a JSON array of strings only, same length and order.'."\n\n"
            .json_encode(array_values($texts), JSON_UNESCAPED_UNICODE);

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout(60)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$this->model.':generateContent', [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
            ])
            ->throw();

        $done = json_decode((string) $response->json('candidates.0.content.parts.0.text'), true);
        if (! is_array($done) || count($done) !== count($texts)) {
            // Never store a half or shifted answer: the texts stay as they are.
            throw new RuntimeException('Gemini returned an unexpected answer');
        }

        return array_map(fn ($t) => trim((string) $t), array_values($done));
    }

    public function name(): string
    {
        return 'gemini';
    }
}
