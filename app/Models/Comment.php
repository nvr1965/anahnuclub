<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    use HasContentTranslations;

    protected array $translatable = ['body'];

    protected $fillable = ['post_id', 'parent_id', 'user_id', 'body', 'locale', 'legacy_source', 'legacy_id', 'created_at', 'updated_at', 'edited_at', 'original_body'];

    protected $casts = ['edited_at' => 'datetime'];

    /** The comment as plain text, for its author to rewrite it (see Html::fromPlainText). */
    public function editableText(): string
    {
        return html_entity_decode(strip_tags(preg_replace('~<br\s*/?>\R?~i', "\n", $this->body) ?? $this->body), ENT_QUOTES | ENT_HTML5);
    }

    /** Its author may rewrite it; comments brought over from the old site stay as they were. */
    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id && $this->legacy_source === null;
    }

    /** Its author, the post's author or the member on whose profile it stands, the group's moderators and the team. */
    public function canBeDeletedBy(?User $user, Post $post): bool
    {
        return $user !== null && ($this->user_id === $user->id || $post->user_id === $user->id || $post->wall_user_id === $user->id
            || $user->is_admin || ($post->group_id && $post->group?->canModerate($user)));
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
