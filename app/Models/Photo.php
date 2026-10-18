<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Photo extends Model
{
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

    public function card(): array
    {
        return [
            'id' => $this->id,
            'thumb' => asset('storage/'.($this->thumb_path ?: $this->path)),
            'url' => asset('storage/'.$this->path),
            'caption' => $this->caption,
        ];
    }
}
