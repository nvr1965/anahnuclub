<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** A club author: an account run by the club that posts news of one country or topic. */
class FeedChannel extends Model
{
    protected $fillable = ['user_id', 'group_id', 'topic', 'timezone', 'daily_min', 'daily_limit', 'every_days', 'stop_words', 'auto_approve', 'paused'];

    protected $casts = ['auto_approve' => 'boolean', 'paused' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The topic group the author posts in; without one, its posts go to the common feed. */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(FeedSource::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }

    /** The author's own time zone: it posts in its own daytime. Without one, the club's. */
    public function tz(): string
    {
        return $this->timezone ?: config('feeds.timezone');
    }

    /** The moment in the author's own time zone (now, by default). */
    public function localTime(?Carbon $at = null): Carbon
    {
        return ($at ?? now())->copy()->tz($this->tz());
    }

    /** The start of the author's posting day (config feeds.window) on the author's own date, in UTC. */
    public function dayStart(?Carbon $at = null): Carbon
    {
        return $this->localTime($at)->setTime(config('feeds.window')[0], 0)->utc();
    }

    /** Today's number of posts: between daily_min and daily_limit, the same all day, different from day to day. */
    public function limitFor(string $day): int
    {
        $min = min($this->daily_min ?? $this->daily_limit, $this->daily_limit);

        return $min + crc32($day.'#'.$this->id) % ($this->daily_limit - $min + 1);
    }

    /**
     * The day's planned moments for the author's posts, in minutes after the author's day opens (in its own time zone): the day is cut
     * into equal parts, one post in each, at a random moment inside the part (the same all day, new every day).
     * So the posts of one author never come together, and the authors do not post at the same hours each day.
     *
     * @return list<int>
     */
    public function slotsFor(string $day, int $count, int $dayMinutes): array
    {
        $part = $dayMinutes / max(1, $count);
        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            $shift = (crc32($day.'#'.$this->id.'#'.$i) % 1000) / 1000;
            $slots[] = (int) floor($part * $i + $part * (0.1 + 0.8 * $shift));
        }

        return $slots;
    }

    /** @return list<string> */
    public function stopWords(): array
    {
        return array_values(array_filter(array_map(fn ($w) => mb_strtolower(trim($w)), preg_split('/[\r\n,]+/', (string) $this->stop_words))));
    }

    /** The accounts of all club authors (they take no friend requests and no messages). */
    public static function authorIds(): Collection
    {
        return once(fn () => static::query()->pluck('user_id'));
    }
}
