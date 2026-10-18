<?php

namespace App\Translation;

/** DeepL where it can, and a second provider (Claude) for the languages DeepL does not translate into. */
class FallbackTranslator implements Translator
{
    public function __construct(private DeepLTranslator $deepl, private ?Translator $backup = null) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return $this->for($targetLocale)->translate($text, $targetLocale, $sourceLocale);
    }

    /**
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $translator = $this->for($targetLocale);

        return $translator instanceof DeepLTranslator
            ? $translator->translateMany($texts, $targetLocale, $sourceLocale)
            : array_map(fn ($t) => $translator->translate($t, $targetLocale, $sourceLocale), $texts);
    }

    /** Whether some provider here can translate into the language. */
    public function supports(string $locale): bool
    {
        return $this->backup !== null || $this->deepl->supports($locale);
    }

    public function name(): string
    {
        return 'deepl';
    }

    private function for(string $locale): Translator
    {
        return $this->backup && ! $this->deepl->supports($locale) ? $this->backup : $this->deepl;
    }
}
