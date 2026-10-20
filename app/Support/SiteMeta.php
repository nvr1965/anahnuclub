<?php

namespace App\Support;

/** The preview a shared link shows (Facebook, WhatsApp, Telegram…) when the page has none of its own. */
class SiteMeta
{
    public static function default(): array
    {
        return [
            'url' => url()->current(),
            'title' => 'anahnu.club',
            'description' => __('The best social network without censorship').'. '.__('Write in your language, everything is translated for you.'),
            'image' => self::image(),
        ];
    }

    /** The invitation picture in the page's language (public/images/invite), English if there is none. */
    public static function image(): string
    {
        $locale = app()->getLocale();

        return asset('images/invite/'.(is_file(public_path("images/invite/$locale.jpg")) ? $locale : 'en').'.jpg');
    }
}
