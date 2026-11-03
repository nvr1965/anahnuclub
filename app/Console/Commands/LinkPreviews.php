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
    protected $signature = 'posts:link-previews {--url= : read just this page now and show what was found}
        {--days= : only links of posts written in the last days (the hourly retry)}';

    protected $description = 'Find links in older posts and fetch their previews in the background';

    public function handle(LinkPreviewFetcher $fetcher): void
    {
        if ($url = $this->option('url')) {
            $found = $fetcher->fetch($url);
            if (! $found) {
                $this->error('Nothing read: '.$fetcher->lastError);

                return;
            }
            LinkPreview::updateOrCreate(['url' => $url], $found);
            foreach ($found as $key => $value) {
                $this->line("$key: $value");
            }

            return;
        }

        $found = 0;
        Post::whereNull('link_url')->select('id', 'body')->chunkById(500, function ($posts) use (&$found) {
            foreach ($posts as $post) {
                $url = LinkPreviewFetcher::firstUrl($post->body) ?? '';
                Post::whereKey($post->id)->update(['link_url' => $url]);
                $found += $url !== '' ? 1 : 0;
            }
        });

        // Without --days (each deploy): only pages never asked yet, so a deploy does not queue
        // hundreds of dead links again. With --days (the hourly retry): recent posts' pages that
        // failed, once the last try is half an hour old (the job skips younger ones anyway).
        $days = $this->option('days');
        $skip = $days
            ? LinkPreview::where(fn ($q) => $q->whereNotNull('title')->orWhereNotNull('image')->orWhere('updated_at', '>', now()->subMinutes(30)))->select('url')
            : LinkPreview::select('url');
        $missing = Post::where('link_url', '!=', '')
            ->when($days, fn ($q, $days) => $q->where('created_at', '>', now()->subDays((int) $days)))
            ->whereNotIn('link_url', $skip)->distinct()->pluck('link_url');
        // A queue of their own, read after the main one: old links never hold up new posts' translations.
        $missing->each(fn ($url) => FetchLinkPreview::dispatch($url)->onQueue(FetchLinkPreview::SLOW_QUEUE));
        $this->info("Links found: $found, pages queued: {$missing->count()}");
    }
}
