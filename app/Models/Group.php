<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use App\Support\ImageStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Group extends Model
{
    use HasContentTranslations;

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['owner_id', 'name', 'slug', 'description', 'locale', 'avatar_path', 'cover_path', 'is_private', 'published'];

    protected function casts(): array
    {
        return ['is_private' => 'boolean', 'published' => 'boolean'];
    }

    /** How a post, an event or a list names the group: in the reader's language. */
    public function card(): array
    {
        return ['id' => $this->id, 'name' => $this->localized('name'), 'slug' => $this->slug];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'approved')->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(GroupInvitation::class);
    }

    public function roleOffers(): HasMany
    {
        return $this->hasMany(GroupRoleOffer::class);
    }

    /** owner, admin, moderator or member; null for someone not (yet) in the group. */
    public function roleOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }
        if ($this->owner_id === $user->id) {
            return 'owner';
        }
        // Asked once per request: every post of a group page checks it.
        $roles = app('group.roles');
        $key = $this->id.':'.$user->id;
        if (! $roles->offsetExists($key)) {
            $pivot = $this->members()->whereKey($user->id)->first()?->pivot;
            $roles[$key] = ! $pivot || ! $pivot->approved ? null : (in_array($pivot->role, ['admin', 'moderator'], true) ? $pivot->role : 'member');
        }

        return $roles[$key];
    }

    /** After roles change, so the next look-up reads them again. */
    public static function forgetRoles(): void
    {
        app('group.roles')->exchangeArray([]);
    }

    /** The owner and admins run the group; moderators also look after its posts. */
    public function canModerate(?User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'admin', 'moderator'], true);
    }

    /** Name or description, in the original or any translation; LIKE wildcards in the text are taken literally. */
    public function scopeMatching(Builder $query, string $text): Builder
    {
        $op = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes($text, '%_\\').'%';

        return $query->where(fn (Builder $w) => $w->where('name', $op, $like)->orWhere('description', $op, $like)
            ->orWhereHas('contentTranslations', fn (Builder $t) => $t->where('text', $op, $like)));
    }

    /**
     * Groups started more than $days ago that never got anything: no posts, albums, videos or events.
     * Albums are checked too, because deleting the group would delete its albums.
     */
    public function scopeStale(Builder $query, int $days = 365): Builder
    {
        $query->where('created_at', '<', now()->subDays($days));
        foreach (['posts', 'albums', 'videos', 'events'] as $table) {
            $query->whereNotExists(fn (QueryBuilder $q) => $q->selectRaw('1')->from($table)->whereColumn($table.'.group_id', 'groups.id'));
        }

        return $query;
    }

    /** Deletes the group with its pictures; members, invitations and blocks go with it in the database. */
    public function purge(): void
    {
        ImageStore::delete($this->avatar_path);
        ImageStore::delete($this->cover_path);
        $this->delete();
    }
}
