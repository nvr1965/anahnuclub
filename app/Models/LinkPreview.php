<?php

namespace App\Models;

use App\Support\ImageStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LinkPreview extends Model
{
    protected $fillable = ['url', 'title', 'description', 'image', 'site_name'];

    protected static function booted(): void
    {
        // A new picture for the page: our copy of the old one goes.
        static::saving(function (LinkPreview $preview) {
            if ($preview->isDirty('image') && $preview->getOriginal('image_path')) {
                ImageStore::delete($preview->getOriginal('image_path'));
                $preview->image_path = null;
            }
        });
        static::deleted(fn (LinkPreview $preview) => ImageStore::delete($preview->image_path));
    }

    /**
     * A page that told us nothing (its site turns servers away) still gets a small card: the site and the address.
     *
     * @return array{url: string, domain: string, title: string|null, description: string|null, image: string|null}
     */
    public function card(): array
    {
        return [
            'url' => $this->url,
            'domain' => preg_replace('/^www\./', '', (string) parse_url($this->url, PHP_URL_HOST)),
            'title' => $this->title,
            // As the site wrote it: only the post's own text is translated.
            'description' => $this->description,
            'image' => $this->imageUrl(),
        ];
    }

    /**
     * The picture always from our own server: our copy, or, until it is made, the address
     * that makes it (an antivirus may distrust the other site's picture server).
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->image ? route('link-previews.image', $this) : null;
    }
}
