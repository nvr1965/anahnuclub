<?php

namespace App\Translation;

use Illuminate\Support\Facades\File;

/**
 * The interface text of each language, in three layers, the later winning:
 * lang/{code}.json from the code; storage/app/lang/{code}.json, machine-translated from English for
 * languages an administrator added; storage/app/lang/{code}.edits.json, texts an administrator
 * corrected by hand, which machine translation never touches. The last two live outside the code
 * so updates of the site never clash with them.
 */
class InterfaceStrings
{
    /** @return array<string, string> */
    public static function read(string $code): array
    {
        return array_merge(self::file(lang_path($code.'.json')), self::file(self::addedPath($code)), self::file(self::editsPath($code)));
    }

    /**
     * Every English string with this language's text and where it comes from: code, auto, manual or missing.
     *
     * @return list<array{key: string, en: string, text: ?string, source: string}>
     */
    public static function rows(string $code): array
    {
        $layers = ['code' => self::file(lang_path($code.'.json')), 'auto' => self::file(self::addedPath($code)), 'manual' => self::file(self::editsPath($code))];
        $rows = [];
        foreach (self::file(lang_path('en.json')) as $key => $en) {
            $source = 'missing';
            foreach ($layers as $name => $strings) {
                if (isset($strings[$key])) {
                    $source = $name;
                }
            }
            $rows[] = ['key' => (string) $key, 'en' => $en, 'text' => $layers[$source][$key] ?? null, 'source' => $source];
        }

        return $rows;
    }

    /** Whether this English key exists, i.e. the interface uses it. */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::file(lang_path('en.json')));
    }

    /** A hand-made text for a key; an empty text drops the correction and the earlier text shows again. */
    public static function correct(string $code, string $key, string $text): void
    {
        $edits = self::file(self::editsPath($code));
        if (trim($text) === '') {
            unset($edits[$key]);
        } else {
            $edits[$key] = $text;
        }
        self::put(self::editsPath($code), $edits);
    }

    /** Drops the machine translation so it is made again; hand-made corrections stay. */
    public static function forgetAuto(string $code): void
    {
        File::delete(self::addedPath($code));
    }

    /** Placeholders such as :name; a text must keep the same ones as the English, or they show raw. @return list<string> */
    public static function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/', $text, $m);
        $found = $m[0];
        sort($found);

        return $found;
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
                $text = trim(self::unprotect($done[$i] ?? ''));
                // A translation that lost or invented a placeholder would show ":name" to people; keep the
                // English instead, so it is not asked for again on every run ("translate again" retries it).
                $added[$key] = $text !== '' && self::placeholders($text) === self::placeholders($chunk[$key]) ? $text : $chunk[$key];
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
        File::delete([self::addedPath($code), self::editsPath($code)]);
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

    private static function editsPath(string $code): string
    {
        return storage_path('app/lang/'.basename($code).'.edits.json');
    }

    private static function write(string $code, array $strings): void
    {
        self::put(self::addedPath($code), $strings);
    }

    private static function put(string $path, array $strings): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    private static function file(string $path): array
    {
        return is_file($path) ? json_decode(file_get_contents($path), true) ?? [] : [];
    }
}
