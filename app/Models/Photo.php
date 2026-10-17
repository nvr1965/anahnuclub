<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    protected $fillable = ['album_id', 'user_id', 'path', 'thumb_path', 'caption', 'created_at', 'updated_at'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
