<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Like extends Model
{
    protected $fillable = ['user_id', 'type', 'likeable_type', 'likeable_id'];

    /** The reactions a member can leave, as on Facebook. */
    public const TYPES = ['like', 'love', 'care', 'haha', 'wow', 'sad', 'angry'];

    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }
}
