<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'body' => Html::clean($this->body),
            'created_at' => $this->created_at?->toIso8601String(),
            'edited' => $this->edited_at !== null,
            // Only for its author: the words to put back in the box when they edit it.
            'editable_text' => $this->resource->canBeEditedBy($request->user()) ? $this->resource->editableText() : null,
            'author' => AuthorResource::make($this->whenLoaded('user')),
            'translation' => TranslationPayload::for($this->resource, app()->getLocale(), app(ContentTranslator::class)),
        ];
    }
}
