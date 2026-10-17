<?php

namespace App\Http\Resources;

use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => Html::clean($this->body),
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => AuthorResource::make($this->whenLoaded('user')),
            'translation' => TranslationPayload::for($this->resource, app()->getLocale(), app(ContentTranslator::class)),
        ];
    }
}
