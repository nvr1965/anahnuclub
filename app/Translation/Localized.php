<?php

namespace App\Translation;

use App\Jobs\TranslateContent;
use App\Support\Background;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Short texts members wrote that are shown as part of the page, not read as content of their own:
 * group names and descriptions, photo captions, album names, titles of links and events, profile
 * "about" lines. They are shown in the reader's language straight away when a translation is stored;
 * otherwise as written, while the translation is made in the background (through the same chain of
 * services and the same cache as posts), so the next page already shows it.
 */
class Localized
{
    /** How long a missing translation is not asked for again (the services may be down or out of today's limit). */
    public const RETRY_MINUTES = 60;

    /** Translations asked for by one page at most; a long list gets the rest on the next views. */
    public const ASKS_PER_REQUEST = 20;

    private static int $asked = 0;

    private static ?object $request = null;

    public static function text(?Model $model, string $field, ?string $locale = null): ?string
    {
        $text = $model?->getAttribute($field);
        if ($model === null || $text === null || trim((string) $text) === '') {
            return $text;
        }
        $locale ??= app()->getLocale();
        $translator = app(ContentTranslator::class);
        if (! $translator->needsTranslation($model, $field, $locale)) {
            return $text;
        }

        $cached = $model->cachedTranslation($field, $locale);
        if ($cached === null && self::ask($model, $locale)) {
            // Where there is no queue and no way to answer first (tests), the job has already run.
            $model->unsetRelation('contentTranslations');
            $cached = $model->cachedTranslation($field, $locale);
        }

        return $cached !== null && trim($cached) !== '' ? $cached : $text;
    }

    /** Asks for the translation once an hour per item and language; whether it was asked now. */
    private static function ask(Model $model, string $locale): bool
    {
        // Counted per request (the app may serve several in one process, as in tests).
        if (self::$request !== request()) {
            self::$request = request();
            self::$asked = 0;
        }
        if (self::$asked >= self::ASKS_PER_REQUEST) {
            return false;
        }
        $key = 'localize.'.$model->getMorphClass().'.'.$model->getKey().'.'.$locale;
        if (! $model->getKey() || ! Cache::add($key, true, now()->addMinutes(self::RETRY_MINUTES))) {
            return false;
        }
        self::$asked++;
        Background::run(new TranslateContent($model, [$locale]));

        return true;
    }
}
