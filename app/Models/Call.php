<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A video or audio call between the two members of a chat. */
class Call extends Model
{
    /** How long a call rings before it counts as missed. */
    public const RING_SECONDS = 45;

    /** A side whose page has not checked in for this long has left the call. */
    public const GONE_SECONDS = 20;

    protected $fillable = ['conversation_id', 'caller_id', 'callee_id', 'video', 'status', 'answered_at', 'ended_at', 'caller_seen_at', 'callee_seen_at'];

    protected function casts(): array
    {
        return [
            'video' => 'boolean',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'caller_seen_at' => 'datetime',
            'callee_seen_at' => 'datetime',
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(CallSignal::class);
    }

    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', ['ringing', 'active']);
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['ringing', 'active'], true);
    }

    public function involves(User $user): bool
    {
        return $this->caller_id === $user->id || $this->callee_id === $user->id;
    }

    public function otherThan(User $user): User
    {
        return $this->caller_id === $user->id ? $this->callee : $this->caller;
    }

    /** Ends a call nobody answered in time, or one a side walked away from without hanging up. */
    public function settle(): self
    {
        $now = now();
        if ($this->status === 'ringing' && ($this->created_at->lt($now->copy()->subSeconds(self::RING_SECONDS))
            || $this->caller_seen_at?->lt($now->copy()->subSeconds(self::GONE_SECONDS)))) {
            $this->update(['status' => 'missed', 'ended_at' => $now]);
        } elseif ($this->status === 'active') {
            $quiet = $now->copy()->subSeconds(self::GONE_SECONDS);
            if (($this->caller_seen_at ?? $this->answered_at)->lt($quiet) || ($this->callee_seen_at ?? $this->answered_at)->lt($quiet)) {
                $this->update(['status' => 'ended', 'ended_at' => $now]);
            }
        }

        return $this;
    }

    /** The member's page checks in. */
    public function touchBy(User $user): void
    {
        $this->update([$this->caller_id === $user->id ? 'caller_seen_at' : 'callee_seen_at' => now()]);
    }
}
