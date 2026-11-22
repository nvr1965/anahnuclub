<?php

use App\Http\Controllers\AlbumController;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The news already published from the sources switched off for legal reasons (Al Jazeera, IRNA, Mehr, Fars, AP via
 * feedx.net) is deleted, as a post deleted by an admin: with its comments, likes and the pictures added with it.
 */
return new class extends Migration
{
    private const URLS = [
        'https://www.aljazeera.net/aljazeerarss/a7c186be-1baa-4bd4-9d80-a84db769f779/73d0e1b4-532f-45ef-b135-bfdff8b8cab9',
        'https://www.aljazeera.com/xml/rss/all.xml',
        'https://www.irna.ir/rss',
        'https://www.mehrnews.com/rss',
        'https://www.farsnews.ir/rss',
        'https://feedx.net/rss/ap.xml',
    ];

    public function up(): void
    {
        $ids = DB::table('feed_items')
            ->whereIn('feed_source_id', DB::table('feed_sources')->whereIn('url', self::URLS)->select('id'))
            ->whereNotNull('post_id')->pluck('post_id');
        Post::whereIn('id', $ids)->each(function (Post $post) {
            $post->photos()->whereHas('album', fn ($q) => $q->where('kind', 'timeline'))->get()->each(fn ($photo) => AlbumController::deletePhoto($photo));
            $post->delete();
        });
    }

    public function down(): void
    {
        // Deleted posts do not come back.
    }
};
