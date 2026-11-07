<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** A club author: an account run by the club that posts news of one country or topic. */
class FeedChannel extends Model
{
    protected $fillable = ['user_id', 'topic', 'daily_min', 'daily_limit', 'stop_words', 'auto_approve', 'paused'];

    protected $casts = ['auto_approve' => 'boolean', 'paused' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(FeedSource::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }

    /** Today's number of posts: between daily_min and daily_limit, the same all day, different from day to day. */
    public function limitFor(string $day): int
    {
        $min = min($this->daily_min ?? $this->daily_limit, $this->daily_limit);

        return $min + crc32($day.'#'.$this->id) % ($this->daily_limit - $min + 1);
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
