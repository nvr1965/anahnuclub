<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasContentTranslations;

    protected array $translatable = ['body'];

    protected $fillable = ['post_id', 'user_id', 'body', 'locale', 'legacy_source', 'legacy_id', 'created_at', 'updated_at'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
