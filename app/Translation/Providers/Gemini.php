<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Translation\Providers;

use App\Models\Language;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Google Gemini: many languages DeepL lacks (Uzbek, Georgian, Persian, ...); the API has a free tier. */
class Gemini extends Provider
{
    /** Pauses before the second and third try when Gemini is busy, in milliseconds. */
    public const RETRY_MS = [2000, 5000];

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

        // 503 "model overloaded" (and other 5xx) on the free tier usually passes in seconds: try twice more
        // before the chain moves on. A 429 is a used-up quota and is not retried.
        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout(30)
            ->retry(self::RETRY_MS, when: fn (Throwable $e) => $e instanceof RequestException && $e->response->serverError(), throw: false)
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
