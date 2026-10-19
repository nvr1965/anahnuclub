<?php

namespace App\Models;

use App\Jobs\FetchLinkPreview;
use App\Models\Concerns\HasContentTranslations;
use App\Support\LinkPreviewFetcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    use HasContentTranslations;

    protected array $translatable = ['title', 'body'];

    protected $fillable = ['user_id', 'group_id', 'video_id', 'event_id', 'type', 'title', 'body', 'link_url', 'locale', 'legacy_source', 'legacy_id', 'audience', 'created_at', 'updated_at'];

    public const AUDIENCES = ['public', 'friends', 'except', 'specific', 'only_me'];

    protected static function booted(): void
    {
        // The first link in the text becomes a card; its page is read once, in the background.
        static::saving(function (Post $post) {
            if ($post->isDirty('body') || $post->link_url === null) {
                $post->link_url = LinkPreviewFetcher::firstUrl($post->body) ?? '';
            }
        });
        static::saved(function (Post $post) {
            if (($post->wasRecentlyCreated || $post->wasChanged('link_url')) && $post->link_url !== '' && ! LinkPreview::where('url', $post->link_url)->exists()) {
                FetchLinkPreview::dispatch($post->link_url);
            }
        });
    }

    public function linkPreview(): BelongsTo
    {
        return $this->belongsTo(LinkPreview::class, 'link_url', 'url');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('id');
    }

    /** The friends a post with audience "except" leaves out, or "specific" is shown to. */
    public function audienceUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_audience');
    }

    /**
     * Only the posts $viewer may see by their audience: public ones, their own, and those their
     * friendship with the author opens. Guests see public posts only.
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $query->where(function (Builder $q) use ($viewer) {
            $q->where('posts.audience', 'public');
            if (! $viewer) {
                return;
            }
            $friends = $viewer->friendIds();
            $named = fn ($sub) => $sub->selectRaw('1')->from('post_audience')->whereColumn('post_audience.post_id', 'posts.id')->where('post_audience.user_id', $viewer->id);
            $q->orWhere('posts.user_id', $viewer->id)
                ->orWhere(fn (Builder $q) => $q->where('posts.audience', 'friends')->whereIn('posts.user_id', $friends))
                ->orWhere(fn (Builder $q) => $q->where('posts.audience', 'except')->whereIn('posts.user_id', $friends)->whereNotExists($named))
                ->orWhere(fn (Builder $q) => $q->where('posts.audience', 'specific')->whereExists($named));
        });
    }

    public function isVisibleTo(?User $viewer): bool
    {
        return self::query()->whereKey($this->id)->visibleTo($viewer)->exists();
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
