<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A private chat between members (for now always two people). */
class Conversation extends Model
{
    protected $fillable = ['last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at', 'accepted_at', 'declined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * The chat between two members, started by $a if they have none yet. For a non-friend $b it is a
     * request that waits apart until $b accepts or answers it.
     */
    public static function between(User $a, User $b): self
    {
        $existing = self::query()
            ->whereHas('users', fn ($q) => $q->whereKey($a->id))
            ->whereHas('users', fn ($q) => $q->whereKey($b->id))
            ->has('users', '=', $a->is($b) ? 1 : 2)
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = self::create();
        $conversation->users()->attach($a->id, ['accepted_at' => now()]);
        if (! $a->is($b)) {
            $conversation->users()->attach($b->id, ['accepted_at' => $a->friendshipWith($b) === 'friends' ? now() : null]);
        }

        return $conversation;
    }
}
