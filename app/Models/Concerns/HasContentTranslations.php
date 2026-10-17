<?php

namespace App\Models\Concerns;

use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Content written by users that is machine-translated into the reader's interface language.
 * Models list the fields in $translatable.
 */
trait HasContentTranslations
{
    public function contentTranslations(): MorphMany
    {
        return $this->morphMany(ContentTranslation::class, 'translatable');
    }

    /** @return list<string> */
    public function translatableFields(): array
    {
        return $this->translatable ?? [];
    }

    public function cachedTranslation(string $field, string $locale): ?string
    {
        $hash = hash('sha256', (string) $this->getAttribute($field));

        return $this->contentTranslations
            ->first(fn (ContentTranslation $t) => $t->field === $field && $t->locale === $locale && $t->source_hash === $hash)
            ?->text;
    }
}
