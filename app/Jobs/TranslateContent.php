<?php

namespace App\Jobs;

use App\Models\Language;
use App\Translation\ContentTranslator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/** Pre-translates new content into every enabled language so readers see it at once. */
class TranslateContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Model $model) {}

    public function handle(ContentTranslator $translator): void
    {
        foreach (Language::enabled() as $language) {
            foreach ($this->model->translatableFields() as $field) {
                $translator->translate($this->model, $field, $language->code);
            }
        }
    }
}
