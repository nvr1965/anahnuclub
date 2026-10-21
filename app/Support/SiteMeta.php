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
     *
     * @return array<string, string>
     */
    public static function alternates(?string $url = null): array
    {
        $url ??= self::here();

        return Language::enabled()->mapWithKeys(fn (Language $language) => [$language->code => self::in($url, $language->code)])
            ->put('x-default', $url)->all();
    }

    /** This page's address in the language asked for in the address, if any; other parameters are left out. */
    public static function canonical(): string
    {
        $asked = request()->query('lang');

        return is_string($asked) && Language::isEnabled($asked) ? self::in(self::here(), $asked) : self::here();
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
