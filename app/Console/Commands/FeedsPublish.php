<?php

namespace App\Console\Commands;

use App\Feeds\FeedPublisher;
use Illuminate\Console\Command;

/** Posts the next approved item, if an author's turn has come (see FeedPublisher). */
class FeedsPublish extends Command
{
    protected $signature = 'feeds:publish';

    protected $description = 'Post the next item of the club authors when its time has come';

    public function handle(FeedPublisher $publisher): int
    {
        $item = $publisher->publishNext();
        $this->line($item ? 'Posted: '.$item->title : 'Nothing to post now.');

        return self::SUCCESS;
    }
}
