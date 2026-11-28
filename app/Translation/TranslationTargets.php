<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Translation;

use App\Models\FeedChannel;
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
 *
 * Only members who were on the site lately count, and never the club's news authors: one account that
 * tried Uzbek once, or a news channel set to some language, must not put every post into that language.
 */
class TranslationTargets
{
    /** How long a post stays "recent" for languages translated only when opened. */
    public const ON_OPEN_DAYS = 7;

    /** A member's languages count for this many days after they were last on the site (or joined). */
    public const ACTIVE_DAYS = 30;

    /** @return Collection<int, string> codes, in the languages' own order */
    public static function eager(): Collection
    {
        $read = Cache::remember('translation-targets.read', now()->addMinutes(10), function () {
            $readers = fn () => User::query()
                ->whereNotIn('id', FeedChannel::authorIds())
                ->where(fn ($q) => $q->where('last_seen_at', '>=', now()->subDays(self::ACTIVE_DAYS))
                    ->orWhere('created_at', '>=', now()->subDays(self::ACTIVE_DAYS)));

            return $readers()->whereNotNull('locale')->distinct()->pluck('locale')
                ->merge($readers()->whereNotNull('ui_locale')->distinct()->pluck('ui_locale'))
                ->push(config('app.locale'))
                ->unique()->values()->all();
        });

        return Language::enabled()->pluck('code')->intersect($read)->values();
    }

    public static function isEager(string $locale): bool
    {
        return self::eager()->contains($locale);
    }
}
