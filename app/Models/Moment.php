<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use App\Support\ImageStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A photo, a short video or a few words on a coloured background, shown for a day.
 * After that only its author sees it, in their archive, for a few more days; then it is deleted.
 */
class Moment extends Model
{
    use HasContentTranslations, Prunable;

    public const HOURS = 24;

    public const ARCHIVE_DAYS = 3;

    public const MAX_SECONDS = 30;

    public const AUDIENCES = ['friends', 'members', 'list'];

    public const POSITIONS = ['top', 'center', 'bottom'];

    /** Backgrounds of a moment of words, also behind the words on a picture. */
    public const BACKGROUNDS = ['brand', 'sunset', 'ocean', 'forest', 'night', 'rose'];

    protected array $translatable = ['text'];

    protected $fillable = ['user_id', 'kind', 'path', 'thumb_path', 'text', 'text_position', 'background', 'locale', 'audience', 'friend_list_id', 'duration', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(function (Moment $moment) {
            if ($moment->path) {
                Storage::disk('public')->delete($moment->path);
            }
            ImageStore::delete($moment->thumb_path);
        });
    }

    /** Gone from the archive too: the files and the row are deleted (hourly, by the scheduler). */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDays(self::ARCHIVE_DAYS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friendList(): BelongsTo
    {
        return $this->belongsTo(FriendList::class);
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'moment_views')->withPivot('viewed_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('moments.expires_at', '>', now());
    }

    /** Moments $viewer may watch: their own, and those of others whose audience includes them. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        $hidden = $viewer->hiddenUserIds();

        return $query->where(function (Builder $q) use ($viewer, $hidden) {
            $q->where('moments.user_id', $viewer->id)->orWhere(function (Builder $q) use ($viewer, $hidden) {
                $q->when($hidden->isNotEmpty(), fn (Builder $q) => $q->whereNotIn('moments.user_id', $hidden))
                    ->whereHas('user', fn (Builder $u) => $u->where('blocked', false))
                    ->where(fn (Builder $q) => $q
                        ->where('moments.audience', 'members')
                        ->orWhere(fn (Builder $q) => $q->where('moments.audience', 'friends')->whereIn('moments.user_id', $viewer->friendIds()))
                        ->orWhere(fn (Builder $q) => $q->where('moments.audience', 'list')->whereExists(
                            fn ($sub) => $sub->selectRaw('1')->from('friend_list_user')
                                ->whereColumn('friend_list_user.friend_list_id', 'moments.friend_list_id')
                                ->where('friend_list_user.user_id', $viewer->id)
                        )));
            });
        });
    }

    public function isVisibleTo(User $viewer): bool
    {
        return self::query()->whereKey($this->id)->active()->visibleTo($viewer)->exists();
    }

    public function fileUrl(): ?string
    {
        return $this->path ? asset('storage/'.$this->path) : null;
    }

    public function thumbUrl(): ?string
    {
        return $this->thumb_path ? asset('storage/'.$this->thumb_path) : ($this->kind === 'photo' ? $this->fileUrl() : null);
    }

    public function markSeenBy(User $viewer): void
    {
        if ($viewer->id !== $this->user_id) {
            DB::table('moment_views')->insertOrIgnore(['moment_id' => $this->id, 'user_id' => $viewer->id, 'viewed_at' => now()]);
        }
    }

    /** What the viewer needs to show the moment. */
    public function card(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'file_url' => $this->fileUrl(),
            'thumb' => $this->thumbUrl(),
            'text' => $this->text,
            'text_position' => $this->text_position,
            'background' => $this->background,
            'duration' => $this->duration,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
