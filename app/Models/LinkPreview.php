<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

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
            // As the site wrote it: only the post's own text is translated.
            'description' => $this->description,
            'image' => $this->imageUrl(),
        ];
    }

    /**
     * The picture is only linked, never copied to our server: it is shown from the site it belongs to
     * (the card sends no referrer), so the rights to it stay with its publisher.
     */
    public function imageUrl(): ?string
    {
        return $this->image ?: null;
    }
}
