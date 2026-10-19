<?php

namespace App\Console\Commands;

use App\Jobs\FetchLinkPreview;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Support\LinkPreviewFetcher;
use Illuminate\Console\Command;

/** Finds the links in posts written before link cards existed and queues their pages for reading. */
class LinkPreviews extends Command
{
    protected $signature = 'posts:link-previews';

    protected $description = 'Find links in older posts and fetch their previews in the background';

    public function handle(): void
    {
        $found = 0;
        Post::whereNull('link_url')->select('id', 'body')->chunkById(500, function ($posts) use (&$found) {
            foreach ($posts as $post) {
                $url = LinkPreviewFetcher::firstUrl($post->body) ?? '';
                Post::whereKey($post->id)->update(['link_url' => $url]);
                $found += $url !== '' ? 1 : 0;
            }
        });

        $missing = Post::where('link_url', '!=', '')->whereNotIn('link_url', LinkPreview::select('url'))->distinct()->pluck('link_url');
        $missing->each(fn ($url) => FetchLinkPreview::dispatch($url));
        $this->info("Links found: $found, pages queued: {$missing->count()}");
    }
}
