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

    /** A joke or a recipe is not cut in the middle: the whole of it, when not longer than this. */
    public const FULL_TEXT_LENGTH = 1500;

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
            'summary' => self::excerpt(self::withoutBoilerplate($description)) ?: null,
            // The whole text, for a source whose items are short in full (jokes): FeedSource::full_text.
            'text' => self::fullText($description) ?: null,
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

    /** The whole text with its lines (a joke's dialogue), cut at a word when too long. */
    public static function fullText(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)[^>]*>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace(["/[ \t\x{00A0}]+/u", "/ *\n */", "/\n{3,}/"], [' ', "\n", "\n\n"], $text));
        $text = self::withoutTails($text);

        return mb_strlen($text) <= self::FULL_TEXT_LENGTH ? $text : self::excerpt($text, self::FULL_TEXT_LENGTH);
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
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:—-").'…';
    }
}
