<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Photo extends Model
{
    use HasContentTranslations;

    /** The caption is shown in the reader's language. */
    protected array $translatable = ['caption'];

    protected $fillable = ['album_id', 'user_id', 'post_id', 'path', 'thumb_path', 'caption', 'created_at', 'updated_at'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /** @param  bool  $asWritten  the caption as typed, for its owner to edit */
    public function card(bool $asWritten = false): array
    {
        return [
            'id' => $this->id,
            'thumb' => asset('storage/'.($this->thumb_path ?: $this->path)),
            'url' => asset('storage/'.$this->path),
            'caption' => $asWritten ? $this->caption : $this->localized('caption'),
        ];
    }
}
