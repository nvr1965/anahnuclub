<?php

namespace App\Http\Resources;

use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translator = app(ContentTranslator::class);
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => Html::clean($this->body),
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => AuthorResource::make($this->whenLoaded('user')),
            'group' => $this->whenLoaded('group', fn () => $this->group?->only('id', 'name', 'slug')),
            'likes_count' => $this->likes_count ?? 0,
            'liked' => (bool) ($this->liked ?? false),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'translation' => TranslationPayload::for($this->resource, $locale, $translator),
        ];
    }
}
