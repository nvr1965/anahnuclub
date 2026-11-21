<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A complaint about a post or a comment, and what the team did about it. */
class Report extends Model
{
    public const REASONS = ['spam', 'abuse', 'hate', 'violence', 'illegal', 'copyright', 'privacy', 'other'];

    protected $fillable = [
        'reporter_id', 'email', 'ip', 'reportable_type', 'reportable_id', 'post_id', 'author_id', 'snapshot',
        'reason', 'details', 'status', 'handled_by', 'handled_at', 'note',
    ];

    protected $casts = ['handled_at' => 'datetime'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** The reported post or comment, while it is still there. */
    public function target(): Post|Comment|null
    {
        return match ($this->reportable_type) {
            'post' => Post::find($this->reportable_id),
            'comment' => Comment::find($this->reportable_id),
            default => null,
        };
    }
}
