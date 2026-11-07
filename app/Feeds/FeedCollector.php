<?php

namespace App\Feeds;

use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Moderation\ContentScanner;

/**
 * Takes the new items of a source into the queue (at most three newest a run): each news item once (by its link, and by its title
 * among the last two days, so the same story from two papers is taken once), only fresh ones,
 * and without the words the author's curator keeps out.
 */
class FeedCollector
{
    /** Older news is not news any more. */
    public const MAX_AGE_HOURS = 12;

    /** New items taken from one source per run, the newest: the queue holds a choice, not the whole site. */
    public const PER_RUN = 3;

    public const SAME_TITLE_HOURS = 48;

    public function __construct(private FeedReader $reader) {}

    /** @return int how many items were added */
    public function collect(FeedSource $source): int
    {
        try {
            $items = $this->reader->read($source->url);
        } catch (\Throwable $e) {
            $source->update(['fetched_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return 0;
        }
        $source->update(['fetched_at' => now(), 'error' => null]);

        return $this->store($source, $items);
    }

    /** @param  list<array{title: string, url: string, summary: ?string, image: ?string, published_at: ?\Illuminate\Support\Carbon}>  $items */
    public function store(FeedSource $source, array $items): int
    {
        $channel = $source->channel;
        $stopWords = $channel->stopWords();
        $scanner = new ContentScanner;
        $added = 0;
        usort($items, fn ($a, $b) => ($b['published_at']?->getTimestamp() ?? PHP_INT_MAX) <=> ($a['published_at']?->getTimestamp() ?? PHP_INT_MAX));

        foreach ($items as $item) {
            if ($added >= self::PER_RUN) {
                break;
            }
            if ($item['published_at'] && ($item['published_at']->lt(now()->subHours(self::MAX_AGE_HOURS)) || $item['published_at']->gt(now()->addHour()))) {
                continue;
            }
            $url = FeedItem::normalizeUrl($item['url']);
            $hash = sha1($url);
            $titleKey = FeedItem::titleKey($item['title']);
            if (FeedItem::where('url_hash', $hash)->exists()
                || ($titleKey && FeedItem::where('title_key', $titleKey)->where('created_at', '>=', now()->subHours(self::SAME_TITLE_HOURS))->exists())) {
                continue;
            }

            $text = mb_strtolower($item['title'].' '.$item['summary']);
            $stop = collect($stopWords)->first(fn ($w) => str_contains($text, $w));
            $hits = $scanner->scan([$item['title'], $item['summary']]);
            $reason = match (true) {
                $stop !== null => 'stop word: '.$stop,
                $hits !== [] => 'content check: '.implode(', ', array_keys($hits)),
                default => null,
            };

            FeedItem::create([
                'feed_channel_id' => $channel->id,
                'feed_source_id' => $source->id,
                'url' => $item['url'],
                'url_hash' => $hash,
                'title_key' => $titleKey,
                'title' => $item['title'],
                'summary' => $item['summary'],
                'image' => $item['image'],
                'published_at' => $item['published_at'],
                // Straight to the author's queue, unless its curator wants to look at everything first.
                'status' => $reason ? 'skipped' : ($channel->auto_approve ? 'approved' : 'new'),
                'reason' => $reason,
            ]);
            $added += $reason ? 0 : 1;
        }

        return $added;
    }
}
