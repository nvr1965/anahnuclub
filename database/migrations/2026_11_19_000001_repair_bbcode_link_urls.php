<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

use App\Support\LinkPreviewFetcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Posts from the old site whose link was read out of BBCode with the bracket and the words after it
 * ("https://site.com]words"). Such an address broke the post's page in the browser, which Google
 * reported as a soft 404. Each one is read again from the post's text; the preview is fetched later
 * by posts:link-previews, as for any post without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')->where(fn ($q) => $q->where('link_url', 'like', '%]%')->orWhere('link_url', 'like', '%[%'))
            ->select(['id', 'body'])->orderBy('id')->chunkById(500, function ($posts) {
                foreach ($posts as $post) {
                    DB::table('posts')->where('id', $post->id)->update(['link_url' => LinkPreviewFetcher::firstUrl($post->body) ?? '']);
                }
            });
    }

    public function down(): void
    {
        // The broken addresses are not worth bringing back.
    }
};
