<?php

namespace App\Support;

use App\Models\Language;

/**
 * What search engines and shared links (Facebook, WhatsApp, Telegram…) read from a page:
 * its title, description, picture, address in each language, and a plain-text copy of its content.
 */
class SiteMeta
{
    public static function default(): array
    {
        return [
            'url' => self::canonical(),
            'title' => 'anahnu.club',
            'description' => self::slogan(),
            'image' => self::image(),
            // Sign-in and password forms are of no use in search results.
            'noindex' => request()->routeIs('login', 'password.*', 'verification.*'),
        ];
    }

    /** A page's own title and description; the rest is the site's. */
    public static function page(string $title, ?string $description = null, array $extra = []): array
    {
        return array_merge(self::default(), [
            'title' => $title.' - anahnu.club',
            'description' => $description ?: self::slogan(),
        ], $extra);
    }

    public static function slogan(): string
    {
        return __('The best social network without censorship').'. '.__('Write in your language, everything is translated for you.');
    }

    /** The invitation picture in the page's language (public/images/invite), English if there is none. */
    public static function image(): string
    {
        $locale = app()->getLocale();

        return asset('images/invite/'.(is_file(public_path("images/invite/$locale.jpg")) ? $locale : 'en').'.jpg');
    }

    /**
     * The page in each language is its address with ?lang=he etc.; without it the visitor's
     * browser decides (x-default). Search engines get one address per language this way.
     * A post names only the languages its text is in ($languages); its other versions would be
     * the same text under another menu, which search engines count as duplicates.
     *
     * @param  list<string>|null  $languages  all the site's languages when null
     * @return array<string, string>
     */
    public static function alternates(?string $url = null, ?array $languages = null): array
    {
        $url ??= self::here();

        return Language::enabled()
            ->filter(fn (Language $language) => $languages === null || in_array($language->code, $languages, true))
            ->mapWithKeys(fn (Language $language) => [$language->code => self::in($url, $language->code)])
            ->put('x-default', $url)->all();
    }

    /**
     * This page's address in the language asked for in the address, if any; other parameters are left out.
     * A language the page is not in ($languages) points to the address without one.
     *
     * @param  list<string>|null  $languages
     */
    public static function canonical(?array $languages = null): string
    {
        $asked = request()->query('lang');
        $known = is_string($asked) && Language::isEnabled($asked) && ($languages === null || in_array($asked, $languages, true));

        return $known ? self::in(self::here(), $asked) : self::here();
    }

    public static function in(string $url, string $locale): string
    {
        return $url.'?lang='.$locale;
    }

    /** Whether search engines may index the site: only at its real address, never on a test copy. */
    public static function indexable(): bool
    {
        return (bool) config('seo.indexable');
    }

    /**
     * The club itself in schema.org terms, for the home page: the organisation behind the site and
     * the site in its languages. Search engines show its name and logo from this.
     *
     * @return list<array<string, mixed>>
     */
    public static function site(): array
    {
        $home = route('home').'/';

        return [
            ['@type' => 'Organization', '@id' => $home.'#club', 'name' => 'anahnu.club', 'url' => $home,
                'logo' => asset('images/logo-mark.png'), 'description' => self::slogan()],
            ['@type' => 'WebSite', '@id' => $home.'#site', 'name' => 'anahnu.club', 'url' => $home,
                'publisher' => ['@id' => $home.'#club'], 'inLanguage' => Language::enabled()->pluck('code')->all()],
        ];
    }

    /**
     * schema.org data as the page carries it (<script type="application/ld+json">); "<" is escaped
     * so that text written by members cannot end the script.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function jsonLd(array $nodes): string
    {
        return json_encode(['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * The key that shows IndexNow (Yandex, Bing…) that the club itself announces its new pages.
     * It is served at /{key}.txt. Without INDEXNOW_KEY it is made from the app key, so it needs no setup
     * and stays the same from one deploy to the next.
     */
    public static function indexNowKey(): ?string
    {
        $key = config('seo.indexnow_key') ?: (config('app.key') ? substr(hash('sha256', 'indexnow|'.config('app.key')), 0, 32) : null);

        return $key ?: null;
    }

    /** Plain text of some HTML, for descriptions: no tags, entities or runs of spaces. */
    public static function plain(?string $html, int $length = 200): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5)));

        return mb_strimwidth($text, 0, $length, '…');
    }

    /** The current address; the home page keeps its slash (https://anahnu.club/). */
    private static function here(): string
    {
        $url = url()->current();

        return request()->path() === '/' ? $url.'/' : $url;
    }
}
