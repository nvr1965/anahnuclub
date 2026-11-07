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
 * Posts the approved items one at a time, spread over the club's day: each author up to its daily
 * number, never two posts of the site within minutes, an author not more often than every hour and a half,
 * and the authors whose day is least filled first. When members write a lot, authors post less.
 */
class FeedPublisher
{
    public function publishNext(): ?FeedItem
    {
        if (self::paused()) {
            return null;
        }
        $tz = config('feeds.timezone');
        [$from, $to] = config('feeds.window');
        $local = now()->tz($tz);
        if ($local->hour < $from || $local->hour >= $to) {
            return null;
        }

        FeedItem::whereIn('status', ['new', 'approved'])->whereNotNull('feed_source_id')
            ->where('created_at', '<', now()->subHours(config('feeds.stale_hours')))
            ->update(['status' => 'skipped', 'reason' => 'stale']);

        $last = FeedItem::max('posted_at');
        if ($last && Carbon::parse($last)->gt(now()->subMinutes(config('feeds.site_gap')))) {
            return null;
        }

        $dayStart = $local->copy()->setTime($from, 0)->utc();
        $passed = min(1, $local->copy()->setTime($from, 0)->diffInMinutes($local) / (($to - $from) * 60));
        $factor = self::membersAreBusy() ? 0.5 : 1;
        $posted = FeedItem::where('posted_at', '>=', $dayStart)->selectRaw('feed_channel_id, count(*) as n, max(posted_at) as last')
            ->groupBy('feed_channel_id')->get()->keyBy('feed_channel_id');

        $channel = FeedChannel::query()->where('paused', false)->with('user')
            ->whereHas('items', fn ($q) => $q->where('status', 'approved'))
            ->get()
            ->filter(function (FeedChannel $c) use ($posted, $passed, $factor) {
                $limit = max(1, (int) round($c->daily_limit * $factor));
                $done = (int) ($posted[$c->id]->n ?? 0);
                $last = $posted[$c->id]->last ?? null;

                return $c->user && ! $c->user->blocked && $c->daily_limit > 0
                    && $done < $limit
                    // Spread over the day: by midday about half of the author's number.
                    && $done < floor($limit * $passed) + 1
                    && (! $last || Carbon::parse($last)->lte(now()->subMinutes(config('feeds.author_gap'))));
            })
            ->shuffle()
            ->sortBy(fn (FeedChannel $c) => ($posted[$c->id]->n ?? 0) / max(1, $c->daily_limit))
            ->first();
        if (! $channel) {
            return null;
        }

        // The source used least today first, so one paper does not fill an author's day; then the newest.
        $usedToday = FeedItem::where('feed_channel_id', $channel->id)->where('posted_at', '>=', $dayStart)
            ->selectRaw('feed_source_id, count(*) as n')->groupBy('feed_source_id')->pluck('n', 'feed_source_id');
        $item = FeedItem::where('feed_channel_id', $channel->id)->where('status', 'approved')->with('source')
            ->latest('id')->limit(50)->get()
            ->sortBy([
                fn ($a, $b) => ($usedToday[$a->feed_source_id] ?? 0) <=> ($usedToday[$b->feed_source_id] ?? 0),
                fn ($a, $b) => ($b->published_at ?? $b->created_at) <=> ($a->published_at ?? $a->created_at),
            ])->first();

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
            ], null, $item->source?->locale ?: $channel->user->locale);
        } catch (\Throwable $e) {
            Log::warning('Feed item '.$item->id.' was not posted: '.$e->getMessage());
            $item->update(['status' => 'skipped', 'reason' => 'error: '.mb_substr($e->getMessage(), 0, 200)]);

            return $item;
        }
        $item->update(['status' => 'posted', 'post_id' => $post->id, 'posted_at' => now()]);

        return $item;
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
