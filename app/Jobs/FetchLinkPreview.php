<?php

namespace App\Jobs;

use App\Models\LinkPreview;
use App\Support\LinkPreviewFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Reads a shared link's page once, so posts with it show a card. */
class FetchLinkPreview implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url) {}

    public function handle(LinkPreviewFetcher $fetcher): void
    {
        $known = LinkPreview::where('url', $this->url)->first();
        // An unreachable page is stored empty too, and asked again only after a few hours.
        if ($known && ($known->title || $known->image || $known->updated_at?->gt(now()->subHours(6)))) {
            return;
        }
        $found = $fetcher->fetch($this->url) ?? ['title' => null, 'description' => null, 'image' => null, 'site_name' => null];
        $known ? $known->fill($found)->touch() : LinkPreview::firstOrCreate(['url' => $this->url], $found);
    }
}
