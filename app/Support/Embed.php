<?php

namespace App\Support;

use App\Models\Video;

/**
 * Posts of other sites shown inside a post here: Facebook, Instagram, Telegram, X, YouTube, Vimeo
 * and TikTok. Only addresses of these sites are recognised, and the player's address is always
 * built here from what was read out of the link: nothing a member pasted is put on the page as HTML.
 */
class Embed
{
    /** Scripts that come with the "embed" code of these sites; they are not needed here. */
    private const SCRIPT_HOSTS = 'connect\.facebook\.net|platform\.twitter\.com|(?:www\.)?instagram\.com|(?:www\.)?tiktok\.com|telegram\.org';

    /**
     * The player for a link, or null when the link is not a post or video of a known site.
     *
     * @return array{provider: string, src: string, url: string, height: ?int}|null height null: 16:9
     */
    public static function fromUrl(?string $url): ?array
    {
        $url = html_entity_decode(trim((string) $url), ENT_QUOTES | ENT_HTML5);
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        $host = preg_replace('/^(www|m|mobile|web)\./', '', strtolower($parts['host']));
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);

        if ($host === 'facebook.com' || $host === 'fb.watch') {
            return self::facebook($url, $host, $path, $query);
        }
        if ($host === 'instagram.com' && preg_match('~^/(?:[\w.]+/)?(p|reel|tv)/([\w-]+)~', $path, $m)) {
            $kind = $m[1] === 'tv' ? 'p' : $m[1];

            return self::make('instagram', "https://www.instagram.com/$kind/$m[2]/embed/captioned/", "https://www.instagram.com/$kind/$m[2]/", 640);
        }
        if (($host === 't.me' || $host === 'telegram.me') && preg_match('~^/(?:s/)?([A-Za-z]\w{3,31})/(\d+)/?$~', $path, $m) && ! in_array(strtolower($m[1]), ['joinchat', 'addstickers', 'share'], true)) {
            return self::make('telegram', "https://t.me/$m[1]/$m[2]?embed=1", "https://t.me/$m[1]/$m[2]", 420);
        }
        if (in_array($host, ['twitter.com', 'x.com', 'mobile.twitter.com'], true) && preg_match('~^/\w+/status(?:es)?/(\d+)~', $path, $m)) {
            return self::tweet($m[1], $url);
        }
        if ($host === 'platform.twitter.com' && str_starts_with($path, '/embed/') && ctype_digit((string) ($query['id'] ?? ''))) {
            return self::tweet($query['id'], 'https://x.com/i/status/'.$query['id']);
        }
        if ($host === 'tiktok.com' && preg_match('~^/(?:@[\w.-]+/video|embed(?:/v2)?)/(\d+)~', $path, $m)) {
            return self::make('tiktok', "https://www.tiktok.com/embed/v2/$m[1]", str_contains($path, '/video/') ? $url : "https://www.tiktok.com/embed/v2/$m[1]", 740);
        }
        if ($video = Video::parseUrl($url)) {
            $player = (new Video(['provider' => $video[0], 'provider_id' => $video[1]]))->embedUrl();

            return self::make($video[0], $player, $url, null);
        }

        return null;
    }

    /**
     * Turns the "embed" code copied from one of these sites (an iframe, or a blockquote with its
     * script) into the plain address of the post, which the post then shows as a player.
     * Anything else in the text is left as it is.
     */
    public static function unwrap(string $text): string
    {
        if (! str_contains($text, '<')) {
            return $text;
        }
        $address = function (string $code): ?string {
            preg_match_all('~https?://[^\s"\'<>]+~i', html_entity_decode($code, ENT_QUOTES | ENT_HTML5), $links);
            foreach ($links[0] as $link) {
                if ($embed = self::fromUrl($link)) {
                    return $embed['url'];
                }
            }

            return null;
        };
        $replace = fn (string $pattern, string $text) => preg_replace_callback($pattern, fn ($m) => $address($m[0]) ?? $m[0], $text) ?? $text;

        // Telegram's widget is a script naming the post: data-telegram-post="channel/123".
        $text = preg_replace_callback(
            '~<script\b[^>]*\bdata-telegram-post=["\']([A-Za-z]\w{3,31}/\d+)["\'][^>]*>\s*</script>~i',
            fn ($m) => 'https://t.me/'.$m[1],
            $text,
        ) ?? $text;
        // Facebook's SDK code: <div class="fb-post" data-href="…">…</div>; Instagram, X and TikTok: <blockquote …>…</blockquote>.
        $text = $replace('~<div\b[^>]*\bclass=["\'][^"\']*\bfb-(?:post|video)\b[^>]*>.*?</div>~is', $text);
        $text = $replace('~<blockquote\b[^>]*>.*?</blockquote>~is', $text);
        $text = $replace('~<iframe\b[^>]*>(?:\s*</iframe>)?~i', $text);
        $text = preg_replace('~<script\b[^>]*\bsrc=["\']https?://(?:'.self::SCRIPT_HOSTS.')/[^>]*>\s*</script>|<div id=["\']fb-root["\']>\s*</div>~i', '', $text) ?? $text;

        return trim(preg_replace("~\n{3,}~", "\n\n", $text) ?? $text);
    }

    private static function facebook(string $url, string $host, string $path, array $query): ?array
    {
        // The code of Facebook's "Embed" window points at its plugin, with the post's address inside.
        if (preg_match('~^/plugins/(?:post|video)\.php$~', $path)) {
            $inner = (string) ($query['href'] ?? '');

            return str_contains(strtolower((string) parse_url($inner, PHP_URL_HOST)), 'facebook.com') || str_contains($inner, 'fb.watch') ? self::fromUrl($inner) : null;
        }
        $href = $host === 'fb.watch' ? $url : 'https://www.facebook.com'.$path.(isset($query['story_fbid']) || isset($query['fbid']) || isset($query['v']) ? '?'.http_build_query(array_intersect_key($query, array_flip(['story_fbid', 'fbid', 'id', 'v', 'set']))) : '');
        $video = $host === 'fb.watch' || preg_match('~/videos/|^/reel/|^/watch/?$|^/share/[vr]/~', $path);
        $post = preg_match('~/posts/|^/permalink\.php|^/story\.php|^/photo(?:\.php|/)|/photos/|^/share/p/|^/groups/[^/]+/permalink/~', $path);
        if (! $video && ! $post) {
            return null;
        }

        return $video
            ? self::make('facebook', 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($href).'&show_text=true&width=500', $href, 480)
            : self::make('facebook', 'https://www.facebook.com/plugins/post.php?href='.rawurlencode($href).'&show_text=true&width=500', $href, 600);
    }

    private static function tweet(string $id, string $url): array
    {
        return self::make('x', "https://platform.twitter.com/embed/Tweet.html?id=$id&dnt=true", $url, 520);
    }

    private static function make(string $provider, string $src, string $url, ?int $height): array
    {
        return ['provider' => $provider, 'src' => $src, 'url' => $url, 'height' => $height];
    }
}
