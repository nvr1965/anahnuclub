<?php

namespace App\Jobs;

use App\Models\Language;
use App\Translation\ContentTranslator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/** Pre-translates new content into the enabled languages so readers see it at once. */
class TranslateContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  list<string>|null  $locales  only these languages; all enabled ones when null */
    public function __construct(public Model $model, public ?array $locales = null) {}

    public function handle(ContentTranslator $translator): void
    {
        $enabled = Language::enabled()->pluck('code');
        $locales = $this->locales === null ? $enabled : $enabled->intersect($this->locales);

        foreach ($locales as $locale) {
            foreach ($this->model->translatableFields() as $field) {
                $translator->translate($this->model, $field, $locale);
            }
        }
    }
}
