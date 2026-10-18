<?php

namespace App\Translation;

use Illuminate\Support\Facades\File;

/**
 * The interface text of each language: lang/{code}.json from the code, plus storage/app/lang/{code}.json
 * for languages an administrator added (machine-translated from English, kept apart so updates of
 * the code never clash with them).
 */
class InterfaceStrings
{
    /** @return array<string, string> */
    public static function read(string $code): array
    {
        return array_merge(self::file(lang_path($code.'.json')), self::file(self::addedPath($code)));
    }

    /** English strings this language has no text for yet. @return array<string, string> */
    public static function missing(string $code): array
    {
        return $code === 'en' ? [] : array_diff_key(self::file(lang_path('en.json')), self::read($code));
    }

    public static function total(): int
    {
        return count(self::file(lang_path('en.json')));
    }

    /**
     * Machine-translates missing strings until done or $seconds run out; returns how many are still missing.
     * Placeholders such as :name are kept out of the translation.
     */
    public static function fill(string $code, Translator $translator, ?int $seconds = null): int
    {
        $missing = self::missing($code);
        if ($missing === [] || $translator instanceof NullTranslator) {
            return count($missing);
        }

        $deadline = $seconds ? microtime(true) + $seconds : null;
        $added = self::file(self::addedPath($code));
        foreach (array_chunk($missing, 40, true) as $chunk) {
            $texts = array_map(self::protect(...), array_values($chunk));
            $done = method_exists($translator, 'translateMany')
                ? $translator->translateMany($texts, $code, 'en')
                : array_map(fn ($t) => $translator->translate($t, $code, 'en'), $texts);
            foreach (array_keys($chunk) as $i => $key) {
                $added[$key] = self::unprotect($done[$i] ?? $chunk[$key]);
            }
            self::write($code, $added);
            if ($deadline && microtime(true) > $deadline) {
                break;
            }
        }

        return count(self::missing($code));
    }

    public static function forget(string $code): void
    {
        File::delete(self::addedPath($code));
    }

    private static function protect(string $text): string
    {
        return preg_replace('/:([a-z_]+)/', '<span translate="no">:$1</span>', htmlspecialchars($text, ENT_NOQUOTES));
    }

    private static function unprotect(string $text): string
    {
        $text = preg_replace('#<span translate="no">\s*(:[a-z_]+)\s*</span>#', '$1', $text);

        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
    }

    private static function addedPath(string $code): string
    {
        return storage_path('app/lang/'.basename($code).'.json');
    }

    private static function write(string $code, array $strings): void
    {
        File::ensureDirectoryExists(dirname(self::addedPath($code)));
        File::put(self::addedPath($code), json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    private static function file(string $path): array
    {
        return is_file($path) ? json_decode(file_get_contents($path), true) ?? [] : [];
    }
}
