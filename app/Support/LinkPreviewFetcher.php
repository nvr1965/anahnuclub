<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads a page's title, description and picture (Open Graph tags first, then plain HTML).
 * Members choose the address, so only public web servers are contacted: no private or
 * local addresses, every redirect checked again, a short timeout and a size cap.
 */
class LinkPreviewFetcher
{
    private const MAX_BYTES = 512 * 1024;

    private const MAX_REDIRECTS = 3;

    /** Many news sites turn away anything that does not look like a browser (HTTP 403). */
    private const HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'ru,en;q=0.8,he;q=0.6',
    ];

    /** Why the last fetch() came back empty, for `posts:link-previews --url`. */
    public ?string $lastError = null;

    /** The first web address in a post's text, or null. */
    public static function firstUrl(?string $text): ?string
    {
        if (! $text || ! preg_match('~https?://[^\s<>"\']+~i', html_entity_decode($text, ENT_QUOTES | ENT_HTML5), $m)) {
            return null;
        }
        $url = rtrim($m[0], '.,;:!?)]}»');

        return strlen($url) <= 2048 ? $url : null;
    }

    /** @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}|null */
    public function fetch(string $url): ?array
    {
        $this->lastError = null;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! self::isPublic($url)) {
                $this->lastError = "not a public address: $url";

                return null;
            }
            try {
                $response = Http::timeout(6)->connectTimeout(4)
                    ->withHeaders(self::HEADERS)
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->get($url);
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();

                return null;
            }

            if ($response->redirect()) {
                $next = $response->header('Location');
                if (! $next) {
                    $this->lastError = 'redirect without an address';

                    return null;
                }
                $url = self::absolute($next, $url);

                continue;
            }
            if (! $response->successful() || ! str_contains(strtolower($response->header('Content-Type')), 'html')) {
                $this->lastError = 'HTTP '.$response->status().' '.$response->header('Content-Type');

                return null;
            }

            $body = $response->toPsrResponse()->getBody();
            $html = '';
            while (! $body->eof() && strlen($html) < self::MAX_BYTES) {
                $html .= $body->read(65536);
            }
            $body->close();

            return $this->parse($html, $url, $response->header('Content-Type'));
        }

        $this->lastError = 'too many redirects';

        return null;
    }

    /** @return array{title: ?string, description: ?string, image: ?string, site_name: ?string} */
    public function parse(string $html, string $url, string $contentType = ''): array
    {
        $charset = preg_match('/charset=["\']?([\w-]+)/i', $contentType, $m) ? $m[1]
            : (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $m) ? $m[1] : 'UTF-8');
        if (strcasecmp($charset, 'UTF-8') !== 0) {
            $html = @mb_convert_encoding($html, 'UTF-8', $charset) ?: $html;
        }

        $doc = new \DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NONET);
        $meta = [];
        foreach ($doc->getElementsByTagName('meta') as $tag) {
            $key = strtolower($tag->getAttribute('property') ?: $tag->getAttribute('name'));
            if ($key !== '' && ! isset($meta[$key])) {
                $meta[$key] = trim($tag->getAttribute('content'));
            }
        }
        $title = $doc->getElementsByTagName('title')->item(0)?->textContent;

        $pick = function (array $keys) use ($meta): ?string {
            foreach ($keys as $key) {
                if (($meta[$key] ?? '') !== '') {
                    return $meta[$key];
                }
            }

            return null;
        };
        $clean = fn (?string $s, int $max) => $s === null || trim($s) === ''
            ? null
            : Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5))), $max, '…');

        $image = $pick(['og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src'])
            ?? self::pagePicture($doc);
        $image = $image ? self::absolute($image, $url) : null;

        return [
            'title' => $clean($pick(['og:title', 'twitter:title']) ?? $title, 290),
            'description' => $clean($pick(['og:description', 'twitter:description', 'description']), 490),
            'image' => $image && preg_match('~^https?://~i', $image) && strlen($image) <= 2048 ? $image : null,
            'site_name' => $clean($pick(['og:site_name']), 110),
        ];
    }

    /** A page without a preview picture: its image_src link, else the first picture of the article that is not a logo or an icon. */
    private static function pagePicture(\DOMDocument $doc): ?string
    {
        foreach ($doc->getElementsByTagName('link') as $link) {
            if (strtolower($link->getAttribute('rel')) === 'image_src' && $link->getAttribute('href') !== '') {
                return trim($link->getAttribute('href'));
            }
        }
        $xpath = new \DOMXPath($doc);
        foreach (['//article//img', '//main//img'] as $where) {
            foreach ($xpath->query($where) ?: [] as $img) {
                // Pictures loaded later keep a placeholder in src and the real one in data-src.
                $src = trim($img->getAttribute('data-src') ?: $img->getAttribute('src'));
                $small = fn (string $side) => ($n = (int) $img->getAttribute($side)) > 0 && $n < 200;
                if ($src === '' || str_starts_with($src, 'data:') || preg_match('~\.svg(\?|$)|logo|icon|avatar|sprite|emoji|pixel~i', $src)
                    || $small('width') || $small('height')) {
                    continue;
                }

                return $src;
            }
        }

        return null;
    }

    /** Only http(s) to hosts whose every address is on the public internet. */
    public static function isPublic(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        if (isset($parts['port']) && ! in_array($parts['port'], [80, 443, 8080, 8443], true)) {
            return false;
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );

        return $ips !== [] && collect($ips)->every(
            fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        );
    }

    private static function absolute(string $href, string $base): string
    {
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '//')) {
            return $b['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/');

        return $origin.$dir.$href;
    }
}
