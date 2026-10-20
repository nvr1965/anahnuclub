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
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AnahnuClubBot/1.0; +'.config('app.url').')', 'Accept' => 'text/html,application/xhtml+xml'])
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

        $image = $pick(['og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src']);
        $image = $image ? self::absolute($image, $url) : null;

        return [
            'title' => $clean($pick(['og:title', 'twitter:title']) ?? $title, 290),
            'description' => $clean($pick(['og:description', 'twitter:description', 'description']), 490),
            'image' => $image && preg_match('~^https?://~i', $image) && strlen($image) <= 2048 ? $image : null,
            'site_name' => $clean($pick(['og:site_name']), 110),
        ];
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
