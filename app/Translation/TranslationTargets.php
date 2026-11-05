<?php

namespace App\Translation;

use App\Models\Language;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Which languages new content is translated into right after publishing.
 *
 * Every enabled language costs a translation of every new post, and the free daily limits of the
 * services run out fast when many languages are switched on. So new content goes ahead of time only
 * into the languages the club's members read in, plus the site's own language; a reader in any other
 * enabled language gets a recent post translated when they open it (see TranslationController).
 */
class TranslationTargets
{
    /** How long a post stays "recent" for languages translated only when opened. */
    public const ON_OPEN_DAYS = 7;

    /** @return Collection<int, string> codes, in the languages' own order */
    public static function eager(): Collection
    {
        $read = Cache::remember('translation-targets.read', now()->addMinutes(10), fn () => User::query()
            ->select('locale')->whereNotNull('locale')->distinct()->pluck('locale')
            ->merge(User::query()->select('ui_locale')->whereNotNull('ui_locale')->distinct()->pluck('ui_locale'))
            ->push(config('app.locale'))
            ->unique()->values()->all());

        return Language::enabled()->pluck('code')->intersect($read)->values();
    }

    public static function isEager(string $locale): bool
    {
        return self::eager()->contains($locale);
    }
}
