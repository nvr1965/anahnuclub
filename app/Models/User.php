<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasContentTranslations, HasFactory, Notifiable;

    /** The city is shown in the reader's language. */
    protected array $translatable = ['location'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'locale',
        'ui_locale',
        'messages_from',
        'default_audience',
        'privacy',
        'password',
        'avatar_path',
        'cover_path',
        'status',
        'about',
        'location',
        'country',
        'website',
        'birthdate',
        'gender',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'blocked' => 'boolean',
            'is_admin' => 'boolean',
            'last_seen_at' => 'datetime',
            'rules_accepted_at' => 'datetime',
            'birthdate' => 'date:Y-m-d',
            'privacy' => 'array',
        ];
    }

    protected $appends = ['avatar'];

    /** Letters to the member go out in their default language, not the one the interface is switched to. */
    public function preferredLocale(): ?string
    {
        return $this->locale ?? $this->ui_locale;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function getAvatarAttribute(): ?string
    {
        return $this->avatar_path ? asset('storage/'.$this->avatar_path) : null;
    }

    public function getCoverAttribute(): ?string
    {
        return $this->cover_path ? asset('storage/'.$this->cover_path) : null;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('role', 'approved')->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)->withPivot('last_read_at');
    }

    /**
     * Friendships are one row per pair: user_id asked, friend_id answers by accepting.
     * Friends are the accepted rows in either direction.
     */
    public function friendIds(): Collection
    {
        return DB::table('friendships')->where('accepted', true)->where('user_id', $this->id)->pluck('friend_id')
            ->merge(DB::table('friendships')->where('accepted', true)->where('friend_id', $this->id)->pluck('user_id'))
            ->unique()->values();
    }

    public function friends(): Builder
    {
        return self::query()->whereIn('id', $this->friendIds());
    }

    /** People who asked this member to be friends and are waiting for an answer. */
    public function friendRequesters(): Builder
    {
        return self::query()->whereIn('id', DB::table('friendships')->where('accepted', false)->where('friend_id', $this->id)->select('user_id'));
    }

    /** none, friends, sent (this member asked $other) or received ($other asked this member). */
    public function friendshipWith(User $other): string
    {
        $row = DB::table('friendships')
            ->where(fn ($q) => $q->where('user_id', $this->id)->where('friend_id', $other->id))
            ->orWhere(fn ($q) => $q->where('user_id', $other->id)->where('friend_id', $this->id))
            ->first();

        return match (true) {
            $row === null => 'none',
            (bool) $row->accepted => 'friends',
            $row->user_id === $this->id => 'sent',
            default => 'received',
        };
    }

    /** @var array<string, Collection>|null per-request memo of block lists */
    private ?array $blockMemo = null;

    /** Members this one blocked, plus members who blocked this one: either way they do not meet. */
    /** Seen on the site in the last five minutes; open pages check in every half minute. */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    /** Members on the site now go first; the order given after this sorts the rest. */
    public function scopeOnlineFirst(Builder $query): Builder
    {
        return $query->orderByRaw('case when users.last_seen_at > ? then 0 else 1 end', [now()->subMinutes(5)]);
    }

    /** Most recently here first, never-seen last (PostgreSQL would put empty dates first). */
    public function scopeLatestSeen(Builder $query): Builder
    {
        return $query->orderByRaw('users.last_seen_at is null')->orderByDesc('users.last_seen_at');
    }

    /** With a picture, or on the site now: lists of faces leave out the rest. */
    public function scopeShowable(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('users.avatar_path')->orWhere('users.last_seen_at', '>', now()->subMinutes(5)));
    }

    /** The main administrator, see config app.super_admin_email. */
    public function isSuperAdmin(): bool
    {
        if (! $this->is_admin) {
            return false;
        }
        $email = config('app.super_admin_email');

        return $email
            ? strcasecmp($this->email, $email) === 0
            : $this->id === static::query()->where('is_admin', true)->min('id');
    }

    public function hiddenUserIds(): Collection
    {
        return $this->blockMemo['users'] ??= DB::table('user_blocks')->where('user_id', $this->id)->pluck('blocked_id')
            ->merge(DB::table('user_blocks')->where('blocked_id', $this->id)->pluck('user_id'))
            ->unique()->values();
    }

    public function blockedGroupIds(): Collection
    {
        return $this->blockMemo['groups'] ??= DB::table('group_blocks')->where('user_id', $this->id)->pluck('group_id');
    }

    public function hasBlocked(User $other): bool
    {
        return DB::table('user_blocks')->where('user_id', $this->id)->where('blocked_id', $other->id)->exists();
    }

    /** Either of the two blocked the other. */
    public function isBlockedWith(User $other): bool
    {
        return $this->hiddenUserIds()->contains($other->id);
    }

    public function forgetBlocks(): void
    {
        $this->blockMemo = null;
    }

    /** Profile details that each have their own audience. */
    public const PRIVATE_FIELDS = ['location', 'country', 'website', 'birthdate', 'gender', 'friends'];

    /** Whether $viewer may see this profile detail: public, friends or only me (public when never set). */
    public function shows(string $field, ?User $viewer): bool
    {
        if ($viewer && $viewer->is($this)) {
            return true;
        }

        return match ($this->privacy[$field] ?? 'public') {
            'friends' => $viewer !== null && $this->friendshipWith($viewer) === 'friends',
            'only_me' => false,
            default => true,
        };
    }

    /** Whether either of the two shut the messenger to the other (a full block counts too). */
    public function messengerBlockedWith(User $other): bool
    {
        return $this->isBlockedWith($other) || DB::table('message_blocks')
            ->where(fn ($q) => $q->where('user_id', $this->id)->where('blocked_id', $other->id))
            ->orWhere(fn ($q) => $q->where('user_id', $other->id)->where('blocked_id', $this->id))
            ->exists();
    }

    public function hasMessengerBlocked(User $other): bool
    {
        return DB::table('message_blocks')->where('user_id', $this->id)->where('blocked_id', $other->id)->exists();
    }

    /** Whether this member may write to $other: no block, and $other takes messages from them. */
    public function canMessage(User $other): bool
    {
        if ($this->is($other)) {
            return true;
        }

        return ! $other->blocked
            && ! $this->messengerBlockedWith($other)
            && ($other->messages_from !== 'friends' || $this->friendshipWith($other) === 'friends');
    }

    /** People whose messages this member does not see: blocked either way, fully or in the messenger. */
    public function messengerHiddenIds(): Collection
    {
        return $this->blockMemo['messenger'] ??= $this->hiddenUserIds()
            ->merge(DB::table('message_blocks')->where('user_id', $this->id)->pluck('blocked_id'))
            ->merge(DB::table('message_blocks')->where('blocked_id', $this->id)->pluck('user_id'))
            ->unique()->values();
    }

    public function unreadMessagesCount(): int
    {
        return Message::query()
            ->join('conversation_user as cu', fn ($j) => $j->on('cu.conversation_id', '=', 'messages.conversation_id')->where('cu.user_id', $this->id))
            ->where('messages.user_id', '!=', $this->id)
            ->where(fn ($q) => $q->whereNull('cu.last_read_at')->orWhereColumn('messages.created_at', '>', 'cu.last_read_at'))
            ->whereNotIn('messages.user_id', $this->messengerHiddenIds())
            // Requests from non-friends wait in their own list and do not light up the badge.
            ->whereNotNull('cu.accepted_at')
            ->count();
    }
}
