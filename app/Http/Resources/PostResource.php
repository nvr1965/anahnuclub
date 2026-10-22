<?php

namespace App\Http\Resources;

use App\Http\Controllers\EventController;
use App\Models\Like;
use App\Models\Post;
use App\Support\Embed;
use App\Support\Html;
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
        $embed = ! $media && $this->link_url ? Embed::fromUrl($this->link_url) : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => Html::clean($this->body),
            'created_at' => $this->created_at?->toIso8601String(),
            // A club post names nobody to readers; its author still sees themself, to edit it.
            'author' => $official && ! $mine ? null : AuthorResource::make($this->whenLoaded('user')),
            'official' => $official,
            'group' => $this->whenLoaded('group', fn () => $this->group?->only('id', 'name', 'slug')),
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
            'can_delete' => $request->user() && ($request->user()->id === $this->user_id || $request->user()->is_admin || ($this->group_id && $this->group?->canModerate($request->user()))),
            // The text as the author typed it, for editing (only posts written here, not imported HTML).
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map->card()->all(), []),
            'video' => $this->whenLoaded('video', fn () => $this->video?->card()),
            // A shared link as a card; photos or a video, when the post has them, take its place.
            'link' => $this->relationLoaded('linkPreview') && ! $media && ! $embed
                ? $this->linkPreview?->card()
                : null,
            'embed' => $embed,
            // Only what anyone may read can be put on other sites (see EmbedController).
            'embeddable' => ($this->audience ?? 'public') === 'public'
                && (! $this->group_id || ($this->relationLoaded('group') && $this->group && $this->group->published && ! $this->group->is_private)),
            'embed_url' => route('embed.post', $this->id),
            'event' => $this->whenLoaded('event', fn () => $this->event ? EventController::card($this->event) : null),
            'editable_text' => $request->user()?->id === $this->user_id && in_array($this->type, ['status', 'photo', 'video'], true) && $this->legacy_source === null
                ? html_entity_decode(strip_tags(str_replace('<br>', "\n", preg_replace('~<br>\R?~', '<br>', $this->body))), ENT_QUOTES | ENT_HTML5)
                : null,
        ];
    }
}
