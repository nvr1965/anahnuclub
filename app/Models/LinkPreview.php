<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkPreview extends Model
{
    protected $fillable = ['url', 'title', 'description', 'image', 'site_name'];

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
            'description' => $this->description,
            'image' => $this->image,
        ];
    }
}
