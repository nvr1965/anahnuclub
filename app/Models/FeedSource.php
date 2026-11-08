<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An RSS or Atom feed of a newspaper or site, read for one club author. */
class FeedSource extends Model
{
    protected $fillable = ['feed_channel_id', 'name', 'url', 'locale', 'trusted', 'full_text', 'active', 'fetched_at', 'error'];

    protected $casts = ['trusted' => 'boolean', 'full_text' => 'boolean', 'active' => 'boolean', 'fetched_at' => 'datetime'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(FeedChannel::class, 'feed_channel_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }
}
