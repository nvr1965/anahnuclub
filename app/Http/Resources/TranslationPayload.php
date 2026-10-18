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

        // Old content is shown as written; only content just published waits for its translation
        // (being made right after publishing), unless translating while the reader waits is turned on.
        $pending = $pending && (config('services.translation.on_demand') || self::isFresh($model));

        if (! $pending && $fields === []) {
            return null;
        }

        return ['type' => $model->getMorphClass(), 'pending' => $pending, 'fields' => $fields];
    }

    /** Published so recently that its translation may still be on the way. */
    public static function isFresh(Model $model): bool
    {
        return $model->getAttribute('created_at')?->gt(now()->subMinutes(self::FRESH_MINUTES)) ?? false;
    }

    public const FRESH_MINUTES = 10;
}
