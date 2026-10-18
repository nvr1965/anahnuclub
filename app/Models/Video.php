<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** A video from YouTube or Vimeo, or a file uploaded here. */
class Video extends Model
{
    use HasContentTranslations;

    protected array $translatable = ['title', 'description'];

    protected $fillable = ['user_id', 'group_id', 'title', 'description', 'locale', 'provider', 'provider_id', 'url', 'path', 'thumb_path', 'legacy_source', 'legacy_id', 'created_at', 'updated_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /** Where the player loads from: an embed page for YouTube / Vimeo, the file itself otherwise. */
    public function embedUrl(): ?string
    {
        return match ($this->provider) {
            'youtube' => 'https://www.youtube-nocookie.com/embed/'.$this->provider_id,
            'vimeo' => 'https://player.vimeo.com/video/'.$this->provider_id,
            default => null,
        };
    }

    public function fileUrl(): ?string
    {
        return $this->path ? asset('storage/'.$this->path) : null;
    }

    public function thumbUrl(): ?string
    {
        if ($this->thumb_path) {
            return str_contains($this->thumb_path, '://') ? $this->thumb_path : asset('storage/'.$this->thumb_path);
        }

        return $this->provider === 'youtube' ? 'https://i.ytimg.com/vi/'.$this->provider_id.'/hqdefault.jpg' : null;
    }

    /** What a page needs to show the video. */
    public function card(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'provider' => $this->provider,
            'embed_url' => $this->embedUrl(),
            'file_url' => $this->fileUrl(),
            'thumb' => $this->thumbUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Recognises a YouTube or Vimeo link.
     *
     * @return array{0: string, 1: string}|null [provider, id]
     */
    public static function parseUrl(string $url): ?array
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~i', $url, $m)) {
            return ['youtube', $m[1]];
        }
        if (preg_match('~vimeo\.com/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/)?(\d+)~i', $url, $m)) {
            return ['vimeo', $m[1]];
        }

        return null;
    }
}
