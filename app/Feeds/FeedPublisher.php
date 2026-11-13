<?php

namespace App\Feeds;

use App\Models\FeedChannel;
use App\Models\FeedItem;
use App\Models\Post;
use App\Models\Setting;
use App\Support\NewsPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Posts the approved items one at a time, spread over each author's own day in its own time zone (Jake by
 * New York's clock, Farik by Tashkent's): each author has its own times for the day (see FeedChannel::slotsFor),
 * two posts of the site are never closer than 10–25 minutes, an author posts not more often than every hour
 * and a half. When members write a lot, authors post less.
 */
class FeedPublisher
{
    public function publishNext(): ?FeedItem
    {
        self::expire();
        if (self::paused()) {
            return null;
        }

        // Between two posts of the site a pause of a different length each time, so they do not tick like a clock.
        $lastItem = FeedItem::whereNotNull('posted_at')->latest('posted_at')->first(['id', 'posted_at']);
        if ($lastItem && $lastItem->posted_at->gt(now()->subMinutes(self::siteGap($lastItem->id)))) {
            return null;
        }

        // Each author lives in its own time zone: it posts from feeds.window[0] to feeds.window[1] of its own clock,
        // and its day (the number of posts, the planned moments) is its own date there.
        [$from, $to] = config('feeds.window');
        $factor = self::membersAreBusy() ? 0.5 : 1;
        $channels = FeedChannel::query()->where('paused', false)->with(['user', 'group'])
            ->whereHas('items', fn ($q) => $q->where('status', 'approved'))
            ->get()
            ->filter(fn (FeedChannel $c) => ($h = $c->localTime()->hour) >= $from && $h < $to);
        if ($channels->isEmpty()) {
            return null;
        }
        $posted = FeedItem::whereIn('feed_channel_id', $channels->pluck('id'))->where('posted_at', '>=', now()->subDay())
            ->get(['feed_channel_id', 'posted_at'])->groupBy('feed_channel_id');

        // Each author whose next planned moment has come; the one that waits longest goes first.
        $channel = $channels
            ->map(function (FeedChannel $c) use ($posted, $factor, $from, $to) {
                $local = $c->localTime();
                $day = $local->toDateString();
                $dayStart = $c->dayStart();
                $elapsed = $local->copy()->setTime($from, 0)->diffInMinutes($local);
                $mine = ($posted[$c->id] ?? collect())->filter(fn (FeedItem $i) => $i->posted_at->gte($dayStart));
                $slots = $c->slotsFor($day, self::limitToday($c, $day, $factor), ($to - $from) * 60);
                $due = $slots[$mine->count()] ?? null;
                $last = $mine->max('posted_at');
                $ready = $c->user && ! $c->user->blocked && $c->daily_limit > 0
                    && $due !== null && $due <= $elapsed
                    && (! $last || $last->lte(now()->subMinutes(config('feeds.author_gap'))));

                return $ready ? ['channel' => $c, 'due' => $due] : null;
            })
            ->filter()
            ->shuffle()
            ->sortBy('due')
            ->first()['channel'] ?? null;
        if (! $channel) {
            return null;
        }

        $item = self::choose($channel, $channel->dayStart());

        return $item ? $this->publish($item) : null;
    }

    public function publish(FeedItem $item): FeedItem
    {
        $channel = $item->channel;
        $text = trim($item->summary ?: $item->title ?: '');
        if ($item->source) {
            $text .= "\n\n— ".$item->source->name;
        }
        try {
            $post = NewsPost::publish($channel->user, $text, $item->url, [
                'title' => $item->title,
                'description' => $item->summary,
                'image' => $item->image,
            ], $channel->group_id, $item->source?->locale ?: $channel->user->locale);
        } catch (\Throwable $e) {
            Log::warning('Feed item '.$item->id.' was not posted: '.$e->getMessage());
            $item->update(['status' => 'skipped', 'reason' => 'error: '.mb_substr($e->getMessage(), 0, 200)]);

            return $item;
        }
        $item->update(['status' => 'posted', 'post_id' => $post->id, 'posted_at' => now()]);
        // The author was here when it posted: "online" for a few minutes, then "was online" at its own daytime.
        $channel->user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $item;
    }

    /**
     * The author's main news of the moment among its queue: what the curator added by hand first; then a source
     * not yet used today (so one paper does not fill the author's day); then by points: a picture, a real
     * excerpt, a source marked as checked, and freshness (a point less for each three hours of age).
     */
    public static function choose(FeedChannel $channel, Carbon $dayStart): ?FeedItem
    {
        $usedToday = FeedItem::where('feed_channel_id', $channel->id)->where('posted_at', '>=', $dayStart)
            ->selectRaw('feed_source_id, count(*) as n')->groupBy('feed_source_id')->pluck('n', 'feed_source_id');

        return FeedItem::where('feed_channel_id', $channel->id)->where('status', 'approved')->with('source')
            ->latest('id')->limit(100)->get()
            ->sortBy([
                fn ($a, $b) => ($a->feed_source_id !== null) <=> ($b->feed_source_id !== null),
                fn ($a, $b) => ($usedToday[$a->feed_source_id] ?? 0) <=> ($usedToday[$b->feed_source_id] ?? 0),
                fn ($a, $b) => self::points($b) <=> self::points($a),
            ])->first();
    }

    public static function points(FeedItem $item): float
    {
        $age = ($item->published_at ?? $item->created_at)->diffInMinutes(now()) / 60;

        return ($item->image ? 2 : 0)
            + (mb_strlen((string) $item->summary) >= 100 ? 1 : 0)
            + ($item->source?->trusted ? 1 : 0)
            - max(0, $age) / 3;
    }

    /**
     * News not posted within its hours leaves the queue (what the curator added by hand stays until posted),
     * and after two days is deleted; it is kept so long only so the same story is not taken again.
     */
    public static function expire(): void
    {
        $old = now()->subHours(config('feeds.stale_hours'));
        FeedItem::whereIn('status', ['new', 'approved'])->whereNotNull('feed_source_id')
            ->where(fn ($q) => $q->where('published_at', '<', $old)->orWhere(fn ($q) => $q->whereNull('published_at')->where('created_at', '<', $old)))
            ->update(['status' => 'skipped', 'reason' => 'stale']);
        FeedItem::where('status', 'skipped')->where('created_at', '<', now()->subDays(2))->delete();
    }

    /**
     * The author's number of posts today: its daily number, times feeds.frequency for news (the light topics,
     * feeds.light_groups, keep theirs), and half of it on a day the members write a lot themselves.
     * A part of a post is a post on some days: 2 × ⅔ is one post a day and two on every third day.
     * An author that posts once in some days (every_days) has its number spread over them.
     */
    public static function limitToday(FeedChannel $channel, string $day, float $factor = 1): int
    {
        if ($channel->daily_limit <= 0) {
            return 0;
        }
        $light = in_array($channel->group?->slug, config('feeds.light_groups', []), true);
        $expected = $channel->limitFor($day) * $factor * ($light ? 1 : (float) config('feeds.frequency', 1)) / max(1, (int) $channel->every_days);
        $count = (int) floor($expected + 1e-9);
        $part = $expected - $count;

        return $count + ($part > 1e-9 && (crc32('part#'.$day.'#'.$channel->id) % 1000) / 1000 < $part ? 1 : 0);
    }

    /** The author's planned times today in its own time zone, as "HH:MM". @return list<string> */
    public static function plan(FeedChannel $channel, ?Carbon $at = null): array
    {
        [$from, $to] = config('feeds.window');
        $local = $channel->localTime($at);
        $start = $local->copy()->setTime($from, 0);
        $slots = $channel->slotsFor($local->toDateString(), self::limitToday($channel, $local->toDateString()), ($to - $from) * 60);

        return array_map(fn (int $m) => $start->copy()->addMinutes($m)->format('H:i'), $slots);
    }

    /** Minutes between two posts of the site: between the two numbers of feeds.site_gap, by the last post. */
    public static function siteGap(int $lastId): int
    {
        [$min, $max] = (array) config('feeds.site_gap') + [1 => null];
        $max ??= $min;

        return $min + crc32('gap#'.$lastId) % (max($min, $max) - $min + 1);
    }

    public static function paused(): bool
    {
        return Setting::get('feeds_paused') === '1';
    }

    /** Members (not the authors) wrote more than the set number of posts in the last day. */
    public static function membersAreBusy(): bool
    {
        return Post::where('created_at', '>=', now()->subDay())
            ->whereNotIn('user_id', FeedChannel::authorIds())
            ->count() > config('feeds.busy_members');
    }
}
