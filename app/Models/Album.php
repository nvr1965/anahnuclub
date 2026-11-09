<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Album extends Model
{
    use HasContentTranslations;

    /** Shown in the reader's language. */
    protected array $translatable = ['name', 'description'];

    protected $fillable = ['user_id', 'group_id', 'name', 'description', 'kind', 'created_at', 'updated_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function latestPhoto(): HasOne
    {
        return $this->hasOne(Photo::class)->latestOfMany();
    }

    /** The member's album for pictures added to posts, made on first use. */
    public static function timelineOf(User $user): self
    {
        return self::firstOrCreate(['user_id' => $user->id, 'kind' => 'timeline'], ['name' => 'Timeline photos']);
    }
}
