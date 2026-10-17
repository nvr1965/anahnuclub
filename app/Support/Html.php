<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    /** Old Joomla content is HTML; keep the safe formatting and drop everything else. */
    public static function clean(?string $html): string
    {
        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['https', 'http', 'mailto'])
                ->allowMediaSchemes(['https', 'http'])
                ->allowRelativeMedias()
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener nofollow ugc')
                ->forceAttribute('a', 'target', '_blank')
        );

        $html = (string) $html;
        // Joomla wrote image paths relative to its root; those files now live in storage/joomla.
        $html = preg_replace('~(src=["\'])/?images/~i', '$1/storage/joomla/images/', $html) ?? $html;
        if ($html === strip_tags($html)) {
            $html = self::fromPlainText($html);
        }

        return self::$sanitizer->sanitize($html);
    }

    /** Text typed by members: escape it, keep line breaks and make links clickable. */
    public static function fromPlainText(string $text): string
    {
        $escaped = e($text);
        $linked = preg_replace('~(https?://[^\s<]+)~u', '<a href="$1">$1</a>', $escaped) ?? $escaped;

        return nl2br($linked, false);
    }
}
