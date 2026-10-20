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
        $html = $html === strip_tags($html) ? self::fromPlainText($html) : self::bbcode($html);

        return self::$sanitizer->sanitize($html);
    }

    /** Text typed by members: escape it, keep line breaks and make links clickable. */
    public static function fromPlainText(string $text): string
    {
        $escaped = self::bbcode(e($text));
        // Bare addresses become links; ones already inside a tag or a BBCode link stay as they are.
        $linked = preg_replace('~(?<!["=>])(https?://[^\s<"]+?)(?=[.,;:!?)]*(?:\s|<|$))~u', '<a href="$1">$1</a>', $escaped) ?? $escaped;

        return nl2br($linked, false);
    }

    /** JomSocial posts carry BBCode such as [img]…[/img] and [url=…]…[/url]. */
    private static function bbcode(string $html): string
    {
        $rules = [
            '~\[img\]\s*(https?://[^\s\[<"]+)\s*\[/img\]~i' => '<img src="$1" alt="">',
            '~\[url=(https?://[^\]\s<"]+)\](.*?)\[/url\]~is' => '<a href="$1">$2</a>',
            '~\[url\]\s*(https?://[^\s\[<"]+)\s*\[/url\]~i' => '<a href="$1">$1</a>',
            '~\[(/?)(b|i|u)\]~i' => '<$1$2>',
        ];

        return preg_replace(array_keys($rules), array_values($rules), $html) ?? $html;
    }
}
