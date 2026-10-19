<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkPreview extends Model
{
    protected $fillable = ['url', 'title', 'description', 'image', 'site_name'];

    /** @return array{url: string, domain: string, title: string|null, description: string|null, image: string|null}|null */
    public function card(): ?array
    {
        // A page that told us nothing has no card; the link in the text is enough.
        if (! $this->title && ! $this->image) {
            return null;
        }

        return [
            'url' => $this->url,
            'domain' => preg_replace('/^www\./', '', (string) parse_url($this->url, PHP_URL_HOST)),
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
        ];
    }
}
