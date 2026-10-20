<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An offer to $user to become the group's admin, moderator or owner (a hand-over), waiting for an answer. */
class GroupRoleOffer extends Model
{
    public const ROLES = ['admin', 'moderator', 'owner'];

    protected $fillable = ['group_id', 'user_id', 'from_id', 'role'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_id');
    }
}
