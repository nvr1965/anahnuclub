<?php

namespace App\Http\Resources;

use App\Http\Controllers\EventController;
use App\Models\Like;
use App\Models\Post;
use App\Support\Embed;
use App\Support\Html;
use App\Support\PostTags;
use App\Translation\ContentTranslator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translator = app(ContentTranslator::class);
        $locale = app()->getLocale();
        $official = $this->relationLoaded('user') && $this->isOfficial();
        $mine = $request->user()?->id === $this->user_id;
        // A post of Facebook, Instagram, YouTube… linked in the text plays right here; photos or a video of the post come first.
        $media = $this->video_id || ($this->relationLoaded('photos') && $this->photos->isNotEmpty());
        // A share shows the post it carries, not a card of a link in its own words.
        $share = $this->type === 'share' || $this->shared_post_id !== null;
        $embed = ! $media && ! $share && $this->link_url ? Embed::fromUrl($this->link_url) : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            // Hashtags as links, also in posts written before they were made links.
            'body' => PostTags::link(Html::clean($this->body)),
            'created_at' => $this->created_at?->toIso8601String(),
            // A club post names nobody to readers; its author still sees themself, to edit it.
            'author' => $official && ! $mine ? null : AuthorResource::make($this->whenLoaded('user')),
            'official' => $official,
            'group' => $this->whenLoaded('group', fn () => $this->group?->card()),
            // Put on a friend's profile: "Anna ▸ Boris".
            'wall' => $this->whenLoaded('wallUser', fn () => $this->wallUser?->only('id', 'name'), null),
            // Friends tagged beside the author's name: "Anna — with Boris and Vera".
            'with' => $this->relationLoaded('taggedUsers')
                ? $this->taggedUsers->filter(fn ($u) => $u->pivot->kind === 'with')->unique('id')->map(fn ($u) => $u->only('id', 'name'))->values()->all()
                : [],
            // "Add to your post": how the author feels, where they are, a life event.
            'feeling' => $this->feeling,
            'place' => $this->place,
            'life_event' => $this->life_event,
            // The reader is tagged or named in it, and may take the tag off.
            'tagged_me' => $request->user() && $this->relationLoaded('taggedUsers') && $this->taggedUsers->contains('id', $request->user()->id),
            // The post shared; null inside it when it was deleted or this reader may not read it.
            'shared' => $share ? ['post' => $this->relationLoaded('sharedPost') && $this->sharedPost ? self::sharedCard($this->sharedPost, $request) : null] : null,
            // Whether the reader may share it inside the club (its original, for a share).
            'can_share' => $this->canShare($request),
            'likes_count' => $this->likes_count ?? 0,
            'liked' => ($this->my_reaction ?? null) !== null,
            'my_reaction' => $this->my_reaction ?? null,
            // The most left reactions first, three at most, as Facebook shows them.
            'reactions' => collect(Like::TYPES)
                ->mapWithKeys(fn ($type) => [$type => (int) ($this->{$type.'_reactions'} ?? 0)])
                ->filter()->sortDesc()->keys()->take(3)->values()->all(),
            'comments' => $official ? [] : CommentResource::collection($this->whenLoaded('comments')),
            'translation' => TranslationPayload::for($this->resource, $locale, $translator),
            'url' => route('posts.show', $this->id),
            'audience' => $this->audience ?? 'public',
            // Who exactly was named or left out, shown only to the author.
            'audience_ids' => $request->user()?->id === $this->user_id && in_array($this->audience, ['except', 'specific'], true)
                ? $this->audienceUsers()->pluck('users.id')->all()
                : [],
            // Pinned at the top of the page it is shown on: the feed, the author's profile or the group.
            'pinned' => ($place = self::place($request)) !== null && $this->{Post::PIN_PLACES[$place]} !== null,
            // The places this reader may pin it to, and whether it is pinned there now.
            'pins' => collect(array_keys(Post::PIN_PLACES))
                ->filter(fn ($p) => $this->resource->canPin($request->user(), $p))
                ->mapWithKeys(fn ($p) => [$p => $this->{Post::PIN_PLACES[$p]} !== null])->all() ?: null,
            'can_delete' => $request->user() && ($request->user()->id === $this->user_id || $request->user()->id === $this->wall_user_id || $request->user()->is_admin || ($this->group_id && $this->group?->canModerate($request->user()))),
            // The text as the author typed it, for editing (only posts written here, not imported HTML).
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map->card()->all(), []),
            'video' => $this->whenLoaded('video', fn () => $this->video?->card()),
            // A shared link as a card; photos or a video, when the post has them, take its place.
            'link' => $this->relationLoaded('linkPreview') && ! $media && ! $embed && ! $share
                ? $this->linkPreview?->card()
                : null,
            'embed' => $embed,
            // Only what anyone may read can be put on other sites (see EmbedController).
            'embeddable' => ($this->audience ?? 'public') === 'public'
                && (! $this->group_id || ($this->relationLoaded('group') && $this->group && $this->group->published && ! $this->group->is_private)),
            'embed_url' => route('embed.post', $this->id),
            'event' => $this->whenLoaded('event', fn () => $this->event ? EventController::card($this->event) : null),
            'editable_text' => $request->user()?->id === $this->user_id && in_array($this->type, ['status', 'photo', 'video', 'share'], true) && $this->legacy_source === null
                ? html_entity_decode(strip_tags(str_replace('<br>', "\n", preg_replace('~<br>\R?~', '<br>', PostTags::editable($this->body)))), ENT_QUOTES | ENT_HTML5)
                : null,
        ];
    }

    private function canShare(Request $request): bool
    {
        if (! $request->user()) {
            return false;
        }
        if ($this->shared_post_id || $this->type === 'share') {
            return $this->relationLoaded('sharedPost') && $this->sharedPost && $this->sharedPost->canBeSharedBy($request->user());
        }

        return $this->resource->canBeSharedBy($request->user());
    }

    /** The shared post inside a share, as Facebook shows it: its author, words, pictures, video or link. */
    private static function sharedCard(Post $post, Request $request): array
    {
        $official = $post->relationLoaded('user') && $post->isOfficial();
        $media = $post->video_id || ($post->relationLoaded('photos') && $post->photos->isNotEmpty());
        $embed = ! $media && $post->link_url ? Embed::fromUrl($post->link_url) : null;

        return [
            'id' => $post->id,
            'type' => $post->type,
            'title' => $post->title,
            'body' => PostTags::link(Html::clean($post->body)),
            'created_at' => $post->created_at?->toIso8601String(),
            'author' => $official ? null : ($post->user ? AuthorResource::make($post->user)->resolve($request) : null),
            'official' => $official,
            'group' => $post->group?->card(),
            'translation' => TranslationPayload::for($post, app()->getLocale(), app(ContentTranslator::class)),
            'url' => route('posts.show', $post->id),
            'audience' => $post->audience ?? 'public',
            'photos' => $post->relationLoaded('photos') ? $post->photos->map->card()->all() : [],
            'video' => $post->relationLoaded('video') ? $post->video?->card() : null,
            'link' => $post->relationLoaded('linkPreview') && ! $media && ! $embed ? $post->linkPreview?->card() : null,
            'embed' => $embed,
            'event' => $post->relationLoaded('event') && $post->event ? EventController::card($post->event) : null,
        ];
    }

    /** Which place's pins the page shows on top. */
    private static function place(Request $request): ?string
    {
        return match (true) {
            $request->routeIs('dashboard', 'home') => 'feed',
            $request->routeIs('people.show') => 'profile',
            $request->routeIs('groups.show') => 'group',
            default => null,
        };
    }
}
