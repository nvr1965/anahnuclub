<?php

namespace App\Http\Resources;

use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Database\Eloquent\Model;

/**
 * What the page needs to show a model in the reader's language:
 * translations already cached, and whether the rest still has to be fetched.
 */
class TranslationPayload
{
    /** @return array{type: string, pending: bool, fields: array<string, string>}|null */
    public static function for(Model $model, string $locale, ContentTranslator $translator): ?array
    {
        $fields = [];
        $pending = false;

        foreach ($model->translatableFields() as $field) {
            if (! $translator->needsTranslation($model, $field, $locale)) {
                continue;
            }
            $cached = $model->cachedTranslation($field, $locale);
            if ($cached === null) {
                $pending = true;
            } else {
                $fields[$field] = $field === 'title' ? $cached : Html::clean($cached);
            }
        }

        if (! $pending && $fields === []) {
            return null;
        }

        return ['type' => $model->getMorphClass(), 'pending' => $pending, 'fields' => $fields];
    }
}
