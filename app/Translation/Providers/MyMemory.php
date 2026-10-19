<?php

namespace App\Translation\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MyMemory: free and needs no key, 5,000 characters a day (50,000 with an e-mail address given).
 * Takes at most 500 bytes at a time, so longer texts go in pieces and are joined again.
 */
class MyMemory extends Provider
{
    private const MAX_BYTES = 450;

    public function __construct(private ?string $email = null) {}

    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $out = [];
        foreach (array_values($texts) as $text) {
            $pieces = array_map(fn (string $piece) => $this->one($piece, $targetLocale, $sourceLocale), self::split($text));
            $out[] = implode(' ', $pieces);
        }

        return $out;
    }

    private function one(string $text, string $target, ?string $source): string
    {
        $response = Http::timeout(30)->get('https://api.mymemory.translated.net/get', array_filter([
            'q' => $text,
            'langpair' => ($source ?: 'autodetect').'|'.$target,
            'de' => $this->email,
        ]))->throw();

        $done = (string) $response->json('responseData.translatedText');
        if ((int) $response->json('responseStatus') !== 200 || $done === '' || str_starts_with($done, 'MYMEMORY WARNING')) {
            throw new RuntimeException('MyMemory: '.($response->json('responseDetails') ?: $done ?: 'no answer'));
        }

        return html_entity_decode($done, ENT_QUOTES | ENT_HTML5);
    }

    /** @return list<string> pieces of at most MAX_BYTES, cut between words */
    public static function split(string $text): array
    {
        if (strlen($text) <= self::MAX_BYTES) {
            return [$text];
        }
        $pieces = [];
        $buffer = '';
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if ($buffer !== '' && strlen($buffer) + 1 + strlen($word) > self::MAX_BYTES) {
                $pieces[] = $buffer;
                $buffer = '';
            }
            $buffer = $buffer === '' ? mb_strcut($word, 0, self::MAX_BYTES) : "$buffer $word";
        }
        if ($buffer !== '') {
            $pieces[] = $buffer;
        }

        return $pieces;
    }

    public function name(): string
    {
        return 'mymemory';
    }
}
