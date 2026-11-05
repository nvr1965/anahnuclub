<?php

namespace App\Jobs;

use App\Models\Language;
use App\Translation\ContentTranslator;
use App\Translation\TranslationTargets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pre-translates new content into the languages the club reads in, so readers see it at once.
 *
 * With many languages one run could outlast the queue's time limit and be killed halfway, so it
 * stops after TIME_BUDGET seconds and leaves the other languages to a new job. Languages no service
 * could take (one was down, or all had used up today's free limit) are tried again later, with
 * growing pauses, until the next day's limits come.
 */
class TranslateContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** A post deleted before its translation ran needs none. */
    public bool $deleteWhenMissingModels = true;

    /** Seconds one run spends before it hands the remaining languages to the next job. */
    public const TIME_BUDGET = 30;

    /** Pauses before the next try of languages that failed, in minutes: about two days in all. */
    public const RETRY_MINUTES = [15, 60, 180, 360, 720, 1440];

    /** @param  list<string>|null  $locales  only these languages; the club's reading languages when null */
    public function __construct(public Model $model, public ?array $locales = null, public int $attempt = 0) {}

    public function handle(ContentTranslator $translator): void
    {
        $enabled = Language::enabled()->pluck('code');
        $locales = ($this->locales === null ? TranslationTargets::eager() : $enabled->intersect($this->locales))->values();
        $started = microtime(true);
        $failed = [];

        foreach ($locales as $i => $locale) {
            if ($i > 0 && microtime(true) - $started > self::TIME_BUDGET) {
                self::dispatch($this->model, $locales->slice($i)->values()->all(), $this->attempt);
                break;
            }
            foreach ($this->model->translatableFields() as $field) {
                $translator->translate($this->model, $field, $locale);
                if ($translator->lastFailed) {
                    $failed[] = $locale;
                }
            }
        }

        if ($failed !== [] && isset(self::RETRY_MINUTES[$this->attempt])) {
            self::dispatch($this->model, array_values(array_unique($failed)), $this->attempt + 1)
                ->delay(now()->addMinutes(self::RETRY_MINUTES[$this->attempt]));
        }
    }
}
