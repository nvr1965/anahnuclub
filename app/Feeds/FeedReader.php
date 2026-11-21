<?php

namespace App\Feeds;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Reads a source into plain items: title, link, short summary, picture, date. A source is an RSS 2.0,
 * RSS 1.0 (RDF) or Atom feed, a public Telegram channel, or one of the sites without a feed (see PageParsers).
 */
class FeedReader
{
    /** The excerpt we show: a short quote with the link, never the whole article. */
    public const SUMMARY_LENGTH = 300;

    /** And never more than this part of the source's own text (a quarter), however short the text. */
    public const SHARE = 0.25;

    /** A quote shorter than this says nothing: such an item is posted with its title and link only. */
    public const MIN_QUOTE = 20;

    /**
     * @return list<array{title: string, url: string, summary: ?string, image: ?string, published_at: ?Carbon}>
     */
    public function read(string $url): array
    {
        $pages = new PageParsers($this);

        return match (PageParsers::kind($url)) {
            'telegram' => $pages->telegram($this->fetch(PageParsers::telegramUrl($url))),
            'panet' => $pages->panet($this->fetch($url)),
            'youtube' => $this->parse($this->fetch($pages->youtubeFeed($url), PageParsers::YOUTUBE_HEADERS)),
            'bokra' => $pages->bokra($this->fetch($url, PageParsers::BOKRA_HEADERS)),
            default => $this->parse($this->fetch($url, ['Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.5'])),
        };
    }

    /** A page or feed of a site; sites without RSS are read as a browser would see them. */
    public function fetch(string $url, array $headers = []): string
    {
        $response = Http::timeout(15)->connectTimeout(6)
            ->withHeaders($headers + [
                'User-Agent' => 'Mozilla/5.0 (compatible; AnahnuClubBot/1.0; +https://anahnu.club)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru,he;q=0.9,ar;q=0.8,en;q=0.7',
            ])
            ->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status());
        }

        return $response->body();
    }

    /**
     * @return list<array{title: string, url: string, summary: ?string, image: ?string, published_at: ?Carbon}>
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string(trim($xml), SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if (! $doc) {
            throw new RuntimeException('Not an RSS or Atom feed');
        }

        $entries = match (true) {
            isset($doc->channel->item) => $doc->channel->item,  // RSS 2.0
            isset($doc->item) => $doc->item,                    // RSS 1.0
            isset($doc->entry) => $doc->entry,                  // Atom
            default => [],
        };
        $items = [];
        foreach ($entries as $entry) {
            $item = isset($doc->entry) ? $this->atom($entry) : $this->rss($entry);
            if ($item['title'] !== '' && filter_var($item['url'], FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $item['url'])) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function rss(SimpleXMLElement $e): array
    {
        $image = (string) ($e->enclosure['url'] ?? '');
        if ($image === '' || ! str_starts_with((string) ($e->enclosure['type'] ?? 'image/'), 'image/')) {
            $image = self::media($e);
        }
        $dc = $e->children('http://purl.org/dc/elements/1.1/');
        $description = (string) $e->description;
        if ($description === '') {
            $description = (string) $e->children('http://purl.org/rss/1.0/modules/content/')->encoded;
        }

        return $this->item((string) $e->title, (string) ($e->link ?: $e->guid), $description, $image, (string) ($e->pubDate ?: $dc->date));
    }

    private function atom(SimpleXMLElement $e): array
    {
        $link = '';
        foreach ($e->link as $l) {
            $rel = (string) ($l['rel'] ?? 'alternate');
            if ($rel === 'alternate' || $link === '') {
                $link = (string) $l['href'];
            }
        }

        // A YouTube video's text is in media:group/media:description.
        $description = (string) ($e->summary ?: $e->content)
            ?: (string) $e->children('http://search.yahoo.com/mrss/')->group->children('http://search.yahoo.com/mrss/')->description;

        return $this->item((string) $e->title, $link, $description, self::media($e), (string) ($e->published ?: $e->updated));
    }

    public function item(string $title, string $url, string $description, string $image, string $date): array
    {
        // A picture inside the description, when the feed gives no other.
        if ($image === '' && preg_match('/<img[^>]+src=["\']([^"\']+)/i', $description, $m)) {
            $image = html_entity_decode($m[1]);
        }
        try {
            $published = $date !== '' ? Carbon::parse($date) : null;
        } catch (\Throwable) {
            $published = null;
        }

        return [
            'title' => mb_substr(self::plain($title), 0, 500),
            'url' => trim($url),
            'summary' => self::quote(self::withoutBoilerplate($description)) ?: null,
            'image' => preg_match('#^https?://#i', $image) ? mb_substr($image, 0, 1000) : null,
            'published_at' => $published,
        ];
    }

    /** The picture of a Media RSS element (media:content, media:thumbnail, also inside media:group). */
    private static function media(SimpleXMLElement $e): string
    {
        $ns = 'http://search.yahoo.com/mrss/';
        $sets = [$e->children($ns)];
        foreach ($e->children($ns)->group as $group) {
            $sets[] = $group->children($ns);
        }
        foreach ($sets as $m) {
            foreach (['content', 'thumbnail'] as $tag) {
                foreach ($m->{$tag} as $el) {
                    $type = (string) ($el->attributes()['medium'] ?? $el->attributes()['type'] ?? 'image');
                    $url = (string) ($el->attributes()['url'] ?? '');
                    if ($url !== '' && (str_starts_with($type, 'image') || $tag === 'thumbnail')) {
                        return $url;
                    }
                }
            }
        }

        return '';
    }

    /** The lines a site adds to every item of its feed: they say nothing about the news. */
    public static function withoutBoilerplate(string $html): string
    {
        return self::withoutTails(self::plain($html));
    }

    private static function withoutTails(string $text): string
    {
        foreach ([
            '/\s*ScienceAlert stories are written, fact-checked.*$/su',
            '/\s*The post .{1,300} appeared first on .*$/su',
            '/\s*(Continue reading|Read more|Читать далее|Подробнее)\s*(…|\.\.\.)?\s*$/iu',
        ] as $re) {
            $text = preg_replace($re, '', $text);
        }

        return $text;
    }

    /** The most of a source's text we may take: a quarter of it, and not more than SUMMARY_LENGTH. */
    public static function allowedLength(string $text): int
    {
        return max(1, min(self::SUMMARY_LENGTH, (int) floor(mb_strlen($text) * self::SHARE)));
    }

    /**
     * The beginning of a source's text, never more than allowedLength() of it, so never the whole text;
     * nothing when a quarter of the text is too short to say anything (MIN_QUOTE).
     */
    public static function quote(string $text): string
    {
        $length = self::allowedLength($text);

        return $length < self::MIN_QUOTE ? '' : self::excerpt($text, $length);
    }

    public static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)[^>]*>/i', ' ', $html)), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** The beginning of the description, cut at a word, with "…" when cut. */
    public static function excerpt(string $html, int $length = self::SUMMARY_LENGTH): string
    {
        $text = self::plain($html);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, max(1, $length - 1));
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:—-").'…';
    }
}
