<?php

namespace App\Models;

use App\Translation\NullTranslator;
use App\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/** A site page (disclaimer, privacy policy…) written in several languages. */
class Page extends Model
{
    protected $fillable = ['slug', 'content', 'in_footer', 'published', 'sort'];

    protected function casts(): array
    {
        return ['content' => 'array', 'in_footer' => 'boolean', 'published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Title and body in $locale, else in the site's main languages, else in whatever exists.
     *
     * @return array{title: string, body: string, locale: string}
     */
    public function in(string $locale): array
    {
        $content = array_filter($this->content ?? [], fn ($v) => trim($v['title'] ?? '') !== '');
        foreach ([$locale, config('app.fallback_locale'), 'ru'] as $code) {
            if (isset($content[$code])) {
                return ['title' => $content[$code]['title'], 'body' => $content[$code]['body'] ?? '', 'locale' => $code];
            }
        }
        $code = array_key_first($content);

        return ['title' => $content[$code]['title'] ?? $this->slug, 'body' => $content[$code]['body'] ?? '', 'locale' => $code ?? $locale];
    }

    /**
     * Fills the languages that have no text yet by machine translation from $from (or the page's
     * best-written language). Returns the codes that were filled.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function translateMissing(Translator $translator, array $codes, ?string $from = null): array
    {
        if ($translator instanceof NullTranslator) {
            return [];
        }
        $source = $from && trim($this->content[$from]['title'] ?? '') !== '' ? ['locale' => $from] + $this->content[$from] : $this->in('ru');
        $content = $this->content ?? [];
        $filled = [];
        foreach ($codes as $code) {
            if ($code === $source['locale'] || trim($content[$code]['title'] ?? '') !== '') {
                continue;
            }
            try {
                $content[$code] = [
                    'title' => $translator->translate($source['title'], $code, $source['locale']),
                    'body' => trim(strip_tags($source['body'] ?? '')) === '' ? '' : $translator->translate($source['body'], $code, $source['locale']),
                    'auto' => true,
                ];
                $filled[] = $code;
            } catch (Throwable $e) {
                Log::warning('Page translation failed', ['page' => $this->slug, 'locale' => $code, 'error' => $e->getMessage()]);
            }
        }
        if ($filled) {
            $this->update(['content' => $content]);
        }

        return $filled;
    }
}
