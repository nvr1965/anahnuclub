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
        if (LinkPreview::where('url', $this->url)->exists()) {
            return;
        }
        // An unreachable page is stored empty too, so it is not asked again and again.
        LinkPreview::firstOrCreate(['url' => $this->url], $fetcher->fetch($this->url) ?? []);
    }
}
