<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One news item (or a joke a curator added) on its way to the feed. */
class FeedItem extends Model
{
    public const STATUSES = ['new', 'approved', 'posted', 'skipped'];

    protected $fillable = ['feed_channel_id', 'feed_source_id', 'url', 'url_hash', 'title_key', 'title', 'summary', 'image', 'published_at', 'status', 'reason', 'post_id', 'posted_at'];

    protected $casts = ['published_at' => 'datetime', 'posted_at' => 'datetime'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(FeedChannel::class, 'feed_channel_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class, 'feed_source_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** The link without the fragment and tracking parameters, so the same page is known under any of its addresses. */
    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! $parts || empty($parts['host'])) {
            return trim($url);
        }
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn ($k) => ! preg_match('/^(utm_|fbclid|gclid|yclid|mc_|ref$|from$)/i', (string) $k), ARRAY_FILTER_USE_KEY);
        ksort($query);

        return strtolower($parts['scheme'] ?? 'https').'://'.strtolower(preg_replace('/^www\./i', '', $parts['host']))
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/')
            .($query ? '?'.http_build_query($query) : '');
    }

    /** The title's letters and digits only, lower case: "Ракета упала!" and "ракета  упала" are one news item. */
    public static function titleKey(?string $title): ?string
    {
        $key = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $title)));

        return $key === '' ? null : mb_substr($key, 0, 120);
    }
}
