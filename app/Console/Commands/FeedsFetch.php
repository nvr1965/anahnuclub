<?php

namespace App\Console\Commands;

use App\Feeds\FeedCollector;
use App\Models\FeedSource;
use Illuminate\Console\Command;

/** Reads the sources of the club's authors that waited longest, and queues their new items. */
class FeedsFetch extends Command
{
    protected $signature = 'feeds:fetch {--all : Read every active source, not only the next few}';

    protected $description = 'Read the news sources of the club authors';

    public function handle(FeedCollector $collector): int
    {
        $sources = FeedSource::query()->where('active', true)
            ->whereHas('channel', fn ($q) => $q->where('paused', false))
            ->orderByRaw('fetched_at is not null')->orderBy('fetched_at')
            ->when(! $this->option('all'), fn ($q) => $q->limit(config('feeds.sources_per_run')))
            ->get();

        foreach ($sources as $source) {
            $added = $collector->collect($source);
            $this->line($source->name.': '.($source->error ? 'error: '.$source->error : $added.' new'));
        }

        return self::SUCCESS;
    }
}
