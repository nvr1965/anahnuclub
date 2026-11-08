<?php

namespace App\Feeds;


/**
 * Sources without RSS, read from their pages: the public web page of a Telegram channel (t.me/s/<channel>),
 * and the Arab sites Panet and Bokra (front page → the newest articles). Taken from the parsers that work
 * on nofinfo; like there, only the beginning of a post with the link to it, a few newest at a time.
 * The pages of these sites change; when a source starts to give nothing, its page layout is to be checked here.
 */
class PageParsers
{
    /** Articles read from a front page per run: each one is one more request to the site. */
    public const ARTICLES = 5;

    /** The posts of a Telegram channel taken per run, the newest. */
    public const TELEGRAM_POSTS = 10;

    public const BOKRA_HEADERS = ['Referer' => 'https://bokra.net/', 'Accept-Language' => 'ar,en-US;q=0.9,en;q=0.8', 'Cookie' => 'bokra_lang=ar'];

    public function __construct(private FeedReader $reader) {}

    /** How a source is read: by the address the curator gave. */
    public static function kind(string $url): string
    {
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url(trim($url), PHP_URL_HOST)));
        $path = strtolower((string) parse_url(trim($url), PHP_URL_PATH));
        $feed = preg_match('/rss|feed|\.xml$/', $path);

        return match (true) {
            str_starts_with(trim($url), '@') || in_array($host, ['t.me', 'telegram.me'], true) => 'telegram',
            in_array($host, ['youtube.com', 'm.youtube.com'], true) && ! str_contains($path, '/feeds/') => 'youtube',
            in_array($host, ['panet.com', 'panet.co.il'], true) && ! $feed => 'panet',
            $host === 'bokra.net' && ! $feed => 'bokra',
            default => 'feed',
        };
    }

    /** YouTube asks for a cookie choice before its pages: the answer "only what is needed". */
    public const YOUTUBE_HEADERS = ['Cookie' => 'SOCS=CAI; CONSENT=YES+1', 'Accept-Language' => 'en-US,en;q=0.8'];

    /**
     * The feed of a YouTube channel or playlist: https://www.youtube.com/@name, /channel/UC…, /c/name, /user/name,
     * or a playlist link (?list=…). A channel's id is read from its page when the link does not carry it.
     */
    public function youtubeFeed(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (! empty($query['list'])) {
            return 'https://www.youtube.com/feeds/videos.xml?playlist_id='.rawurlencode($query['list']);
        }
        if (preg_match('#/channel/(UC[\w-]{22})#', $url, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id='.$m[1];
        }
        $page = $this->reader->fetch($url, self::YOUTUBE_HEADERS);
        if (preg_match('#<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[\w-]{22})"#', $page, $m)
            || preg_match('#"(?:channelId|externalId|browseId)":"(UC[\w-]{22})"#', $page, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id='.$m[1];
        }
        throw new \RuntimeException('YouTube channel not found on the page');
    }

    /** https://t.me/name, t.me/s/name or @name → the channel's public web page https://t.me/s/name. */
    public static function telegramUrl(string $url): string
    {
        $url = trim($url);
        $name = str_starts_with($url, '@') ? substr($url, 1)
            : preg_replace('#^/(s/)?#', '', (string) parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://'.$url, PHP_URL_PATH));

        return 'https://t.me/s/'.explode('/', $name)[0];
    }

    /** The newest posts of a public channel; an album of photos is one post; pinned and empty ones are left out. */
    public function telegram(string $html): array
    {
        $blocks = explode('<div class="tgme_widget_message_bubble">', $html);
        array_shift($blocks);
        $items = [];
        foreach (array_reverse($blocks) as $block) {
            if (count($items) >= self::TELEGRAM_POSTS) {
                break;
            }
            if (! preg_match('#<a class="tgme_widget_message_date" href="(https://t\.me/[^"?]+)#i', $block, $m)) {
                continue;
            }
            if (! preg_match('#<div class="tgme_widget_message_text[^"]*"[^>]*>(.+?)</div>#is', $block, $t)) {
                continue;
            }
            $text = FeedReader::plain($t[1]);
            if (mb_strlen($text) < 20 || preg_match('/^(pinned|закреп)/iu', $text)) {
                continue;
            }
            $image = '';
            if (preg_match('/background-image:\s*url\([\'"]?([^\'")]+)/i', $block, $im) || preg_match('/<video[^>]+poster=[\'"]([^\'"]+)/i', $block, $im)) {
                $image = html_entity_decode($im[1]);
            }
            $date = preg_match('/<time[^>]+datetime="([^"]+)"/i', $block, $d) ? $d[1] : '';
            $items[] = $this->reader->item(self::titleFrom($text), $m[1], htmlspecialchars($text), $image, $date);
        }

        return $items;
    }

    /** Panet: links /story/<number> of the front page, then each article's title, beginning and picture. */
    public function panet(string $html): array
    {
        preg_match_all('#https?://(?:www\.)?panet\.(?:com|co\.il)/story/\d+#', $html, $m);
        $items = [];
        foreach (array_slice(array_unique($m[0]), 0, self::ARTICLES) as $link) {
            try {
                $page = $this->reader->fetch($link);
            } catch (\Throwable) {
                continue;
            }
            $title = preg_match('#<div id="story-subject".*?<h3[^>]*>(.*?)</h3>#s', $page, $t) ? FeedReader::plain($t[1]) : self::ogTitle($page);
            if ($title === '') {
                continue;
            }
            $intro = preg_match('#<div id="story-intro"[^>]*>(.*?)</div>#s', $page, $i) ? $i[1] : '';
            $body = preg_match('#<div id="story-body"[^>]*>(.*?)</div>#s', $page, $b) ? $b[1] : '';
            $items[] = $this->reader->item($title, $link, trim($intro.' '.$body) ?: self::ogDescription($page), self::ogImage($page), '');
        }

        return $items;
    }

    /** Bokra: the <article> cards of the front page, then each /Article-<number>. */
    public function bokra(string $html): array
    {
        preg_match_all('#<article[^>]*>(.*?)</article>#si', $html, $cards);
        $items = [];
        $seen = [];
        foreach ($cards[1] as $card) {
            if (count($items) >= self::ARTICLES) {
                break;
            }
            if (! preg_match('#href=[\'"](?:https?://(?:www\.)?bokra\.net)?/Article-(\d+)#', $card, $m) || isset($seen[$m[1]])) {
                continue;
            }
            $seen[$m[1]] = true;
            $link = 'https://bokra.net/Article-'.$m[1];
            $title = preg_match('#<h3[^>]*>(.*?)</h3>#si', $card, $t) ? FeedReader::plain($t[1]) : '';
            if ($title === '') {
                continue;
            }
            $image = preg_match('/data-src=[\'"]([^\'"]+)/', $card, $p) || preg_match('/<img[^>]+src=[\'"]([^\'"]+)/', $card, $p) ? html_entity_decode($p[1]) : '';
            try {
                $page = $this->reader->fetch($link, self::BOKRA_HEADERS);
            } catch (\Throwable) {
                $page = '';
            }
            $body = preg_match('#<div[^>]+class="[^"]*article-(?:content|body)[^"]*"[^>]*>(.*?)</div>#si', $page, $a) ? $a[1] : self::ogDescription($page);
            $items[] = $this->reader->item($title, $link, $body, $image ?: self::ogImage($page), '');
        }

        return $items;
    }

    /** A post has no title: its first sentence, or its beginning cut at a word. */
    public static function titleFrom(string $text, int $length = 120): string
    {
        $first = preg_split('/(?<=[.!?…])\s|\n/u', $text, 2)[0];

        return mb_strlen($first) <= $length ? $first : rtrim(FeedReader::excerpt($first, $length - 1), '…').'…';
    }

    private static function meta(string $html, string $name): string
    {
        foreach (['/<meta[^>]+(?:property|name)=["\']'.$name.'["\'][^>]+content=["\']([^"\']+)/i', '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']'.$name.'["\']/i'] as $re) {
            if (preg_match($re, $html, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        return '';
    }

    public static function ogImage(string $html): string
    {
        return self::meta($html, 'og:image');
    }

    private static function ogTitle(string $html): string
    {
        return self::meta($html, 'og:title');
    }

    private static function ogDescription(string $html): string
    {
        return self::meta($html, 'og:description');
    }
}
