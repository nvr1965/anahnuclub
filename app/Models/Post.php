<?php

namespace App\Models;

use App\Jobs\FetchLinkPreview;
use App\Models\Concerns\HasContentTranslations;
use App\Support\Background;
use App\Support\LinkPreviewFetcher;
use App\Support\PostTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    use HasContentTranslations;

    protected array $translatable = ['title', 'body'];

    protected $fillable = ['user_id', 'group_id', 'video_id', 'event_id', 'type', 'title', 'body', 'link_url', 'locale', 'legacy_source', 'legacy_id', 'audience', 'shared_post_id', 'wall_user_id', 'created_at', 'updated_at'];

    public const AUDIENCES = ['public', 'friends', 'except', 'specific', 'only_me'];

    /** Where a post can be pinned, and the column that keeps when it was pinned there. */
    public const PIN_PLACES = ['feed' => 'pinned_at', 'profile' => 'profile_pinned_at', 'group' => 'group_pinned_at'];

    protected $casts = ['pinned_at' => 'datetime', 'profile_pinned_at' => 'datetime', 'group_pinned_at' => 'datetime'];

    protected static function booted(): void
    {
        // The first link in the text becomes a card; its page is read once, in the background.
        static::saving(function (Post $post) {
            if ($post->isDirty('body') || $post->link_url === null) {
                $post->link_url = LinkPreviewFetcher::firstUrl($post->body) ?? '';
            }
        });
        static::saved(function (Post $post) {
            // Its #hashtags, so the hashtag's page finds it.
            if ($post->wasRecentlyCreated || $post->wasChanged(['title', 'body'])) {
                PostTags::syncHashtags($post);
            }
            if (($post->wasRecentlyCreated || $post->wasChanged('link_url')) && $post->link_url !== '' && ! LinkPreview::where('url', $post->link_url)->exists()) {
                // Right after the response for a new post. From the console (the import) it goes to the
                // queue instead: "after the response" there means the end of the command, page after page.
                app()->runningInConsole()
                    ? FetchLinkPreview::dispatch($post->link_url)->onQueue(FetchLinkPreview::SLOW_QUEUE)
                    : Background::run(new FetchLinkPreview($post->link_url));
            }
        });
    }

    /** Pinned posts of the place first, the last pinned on top, then the rest newest first. */
    public function scopePinnedFirst(Builder $query, string $place): Builder
    {
        $column = 'posts.'.self::PIN_PLACES[$place];

        return $query->reorder()->orderByRaw("$column is null")->orderByDesc($column)->latest('posts.created_at')->orderByDesc('posts.id');
    }

    /**
     * Who may pin the post where: the main administrator in everyone's feed, the author on their profile,
     * the owner and admins of its group (and the site's administrators) in the group.
     */
    public function canPin(?User $user, string $place): bool
    {
        if (! $user) {
            return false;
        }

        return match ($place) {
            'feed' => $user->isSuperAdmin(),
            'profile' => $this->user_id === $user->id,
            'group' => $this->group_id !== null && ($user->is_admin || in_array($this->group?->roleOf($user), ['owner', 'admin'], true)),
            default => false,
        };
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

    /** The post this one shares (a repost), null when it is not a share or the post was deleted. */
    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shared_post_id');
    }

    /** The friend on whose profile the post was put. */
    public function wallUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wall_user_id');
    }

    /**
     * The post a share of this one carries: the original, never a share of a share, as on Facebook.
     */
    public function shareRoot(): self
    {
        return $this->shared_post_id && $this->sharedPost ? $this->sharedPost : $this;
    }

    /**
     * Whether $user may share the post inside the club: it is not in a private or hidden group, and
     * everyone may read it, or it is the member's own.
     */
    public function canBeSharedBy(?User $user): bool
    {
        if (! $user || ($this->group_id && (! $this->group || $this->group->is_private || ! $this->group->published))) {
            return false;
        }

        return ($this->audience ?? 'public') === 'public' || $this->user_id === $user->id;
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

    /** Friends tagged in the post: "with" beside the author's name, "mention" for an @name in the text. */
    public function taggedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_user_tags')->withPivot('kind')->withTimestamps();
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
                // What friends put on the viewer's profile, the viewer always sees.
                ->orWhere('posts.wall_user_id', $viewer->id)
                // And the posts they are tagged in.
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('post_user_tags')->whereColumn('post_user_tags.post_id', 'posts.id')->where('post_user_tags.user_id', $viewer->id))
                ->orWhere(fn (Builder $q) => $q->where('posts.audience', 'friends')->whereIn('posts.user_id', $friends))
                // A post on a friend's profile is also read by that friend's friends, as on Facebook.
                ->orWhere(fn (Builder $q) => $q->where('posts.audience', 'friends')->whereIn('posts.wall_user_id', $friends))
                ->orWhere(fn (Builder $q) => $q->where('posts.audience', 'except')->whereIn('posts.user_id', $friends)->whereNotExists($named))
                ->orWhere(fn (Builder $q) => $q->where('posts.audience', 'specific')->whereExists($named));
        });
    }

    /** The main administrator's posts speak for the club: no author shown, no comments. */
    public function isOfficial(): bool
    {
        return (bool) $this->user?->isSuperAdmin();
    }

    /**
     * The languages the post reads in: the one it was written in, and those its text is translated
     * into (a translation of an older version of the text does not count). A post with no words has none.
     * Search engines are shown the post only in these, not in every language of the site.
     *
     * @param  Collection<int, ContentTranslation>|null  $translations  the post's translations; loaded when not given
     * @return list<string>
     */
    public function languages(?Collection $translations = null): array
    {
        $field = trim(strip_tags((string) $this->body)) !== '' ? 'body' : (trim((string) $this->title) !== '' ? 'title' : null);
        if (! $field) {
            return [];
        }
        $hash = hash('sha256', (string) $this->getAttribute($field));
        $translated = ($translations ?? $this->contentTranslations()->get(['field', 'locale', 'source_hash']))
            ->filter(fn (ContentTranslation $t) => $t->field === $field && $t->source_hash === $hash)->pluck('locale');
        $languages = $translated->push($this->locale)->filter()->unique();

        return Language::enabled()->pluck('code')->intersect($languages)->values()->all();
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
