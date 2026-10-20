<?php

namespace App\Translation;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Returns a model field in the reader's language, translating once and caching the result.
 */
class ContentTranslator
{
    /** Characters sent to the provider by this instance, to keep within a provider's quota. */
    public int $charsSent = 0;

    public function __construct(private Translator $translator) {}

    public function enabled(): bool
    {
        return ! $this->translator instanceof NullTranslator;
    }

    public function sourceLocale(Model $model, string $field): ?string
    {
        // A member's own language setting says nothing about how they wrote their city.
        $locale = $model instanceof User ? null : $model->getAttribute('locale');

        return $locale ?? LanguageGuesser::guess((string) $model->getAttribute($field));
    }

    public function needsTranslation(Model $model, string $field, string $locale): bool
    {
        return $this->enabled() && $this->isForeign($model, $field, $locale);
    }

    /** Whether the field has text written in a language other than $locale. */
    public function isForeign(Model $model, string $field, string $locale): bool
    {
        $text = (string) $model->getAttribute($field);

        return trim(strip_tags($text)) !== '' && $this->sourceLocale($model, $field) !== $locale;
    }

    public function translate(Model $model, string $field, string $locale): ?string
    {
        if (! $this->needsTranslation($model, $field, $locale)) {
            return null;
        }

        if ($cached = $model->cachedTranslation($field, $locale)) {
            return $cached;
        }

        $text = (string) $model->getAttribute($field);
        $this->charsSent += mb_strlen($text);

        try {
            $translated = $this->translator->translate($text, $locale, $this->sourceLocale($model, $field));
        } catch (Throwable $e) {
            Log::warning('Content translation failed', ['model' => $model::class, 'id' => $model->getKey(), 'error' => $e->getMessage()]);

            return null;
        }

        $model->contentTranslations()->updateOrCreate(
            ['field' => $field, 'locale' => $locale],
            ['text' => $translated, 'provider' => $this->translator->name(), 'source_hash' => hash('sha256', $text)],
        );
        $model->unsetRelation('contentTranslations');

        return $translated;
    }
}
