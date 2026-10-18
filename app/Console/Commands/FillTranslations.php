<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Group;
use App\Models\Language;
use App\Models\Post;
use App\Translation\ContentTranslator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Translates posts, comments and group names into every enabled language ahead of time,
 * newest first, so readers never wait for the provider. Safe to stop and run again.
 */
class FillTranslations extends Command
{
    protected $signature = 'translations:fill
        {--chars= : Stop after sending this many characters (default: TRANSLATION_DAILY_CHARS, 0 = no limit)}
        {--locale=* : Only these languages}
        {--estimate : Only count what is left to translate}';

    protected $description = 'Translate all content that has no stored translation yet';

    private const MAX_FAILURES_IN_A_ROW = 5;

    public function handle(ContentTranslator $translator): int
    {
        if (! $translator->enabled() && ! $this->option('estimate')) {
            $this->error('Translation is off: set TRANSLATION_DRIVER and the provider key in .env, then run php artisan optimize.');

            return self::FAILURE;
        }

        $locales = $this->option('locale') ?: Language::enabled()->pluck('code')->all();
        $budget = (int) ($this->option('chars') ?? config('services.translation.daily_chars'));
        $left = 0;
        $done = 0;
        $failures = 0;

        foreach ($this->models() as $model) {
            foreach ($locales as $locale) {
                foreach ($model->translatableFields() as $field) {
                    if (! $this->missing($translator, $model, $field, $locale)) {
                        continue;
                    }
                    $size = mb_strlen((string) $model->getAttribute($field));

                    if ($this->option('estimate')) {
                        $left += $size;

                        continue;
                    }
                    if ($budget > 0 && $translator->charsSent + $size > $budget) {
                        $this->info("Daily budget reached: {$translator->charsSent} characters, {$done} translations.");

                        return self::SUCCESS;
                    }

                    if ($translator->translate($model, $field, $locale) === null) {
                        if (++$failures >= self::MAX_FAILURES_IN_A_ROW) {
                            $this->error('The provider keeps failing (quota or key?), stopping. Details are in storage/logs/laravel.log.');

                            return self::FAILURE;
                        }
                    } else {
                        $failures = 0;
                        $done++;
                        if ($done % 25 === 0) {
                            $this->line("{$done} translations, {$translator->charsSent} characters…");
                        }
                    }
                }
            }
        }

        if ($this->option('estimate')) {
            $this->info('Characters left to translate: '.number_format($left, 0, '.', ' ').' (into '.implode(', ', $locales).').');
        } else {
            $this->info("Done: {$done} translations, {$translator->charsSent} characters.");
        }

        return self::SUCCESS;
    }

    private function missing(ContentTranslator $translator, Model $model, string $field, string $locale): bool
    {
        return $translator->isForeign($model, $field, $locale) && $model->cachedTranslation($field, $locale) === null;
    }

    /** @return iterable<Model> */
    private function models(): iterable
    {
        yield from Group::with('contentTranslations')->orderBy('id')->lazy();
        yield from Post::with('contentTranslations')->lazyByIdDesc(100);
        yield from Comment::with('contentTranslations')->lazyByIdDesc(100);
    }
}
