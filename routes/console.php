<?php

use App\Jobs\TranslateContent;
use App\Jobs\TranslateInterface;
use App\Models\Comment;
use App\Models\LoginLog;
use App\Models\Moment;
use App\Models\Post;
use App\Support\Badges;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Shared hosting has no long-running workers: cron runs the scheduler every minute
// and the scheduler drains the queue (pre-translation of new posts and comments).
// Tasks run one after another, so the letters come first: a long translation run never holds them up.
// The overlap locks expire after 10 minutes: a worker the host killed must not stop letters for a day.
Schedule::call(fn () => Cache::forever('schedule:last-run', now()->getTimestamp()))->everyMinute()->name('heartbeat');
Schedule::command('queue:work --queue=mail --stop-when-empty --max-time=50 --tries=3 --backoff=60')->everyMinute()->withoutOverlapping(10);
// The main queue first (translations, new posts' cards); older links' pages only when it is empty.
Schedule::command('queue:work --queue=default,previews --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(10);
// New posts and comments are translated right after publishing; if that run was lost (the host
// cut the request short, a service failed for a moment), the queue gets another try within a minute.
Schedule::call(function () {
    foreach ([Post::class, Comment::class] as $model) {
        $model::where('created_at', '>', now()->subMinutes(15))->where('created_at', '<', now()->subMinute())
            ->whereDoesntHave('contentTranslations')->limit(20)->get()
            ->each(fn ($item) => Cache::add('translate-retry.'.$item->getMorphClass().'.'.$item->getKey(), true, now()->addMinutes(5))
                && TranslateContent::dispatch($item));
    }
})->everyMinute()->name('translate-new-content')->withoutOverlapping(5);
// Interface translation of an added language stops when the services hit their daily limits; pick it up again.
Schedule::call([TranslateInterface::class, 'resumeAll'])->hourly()->name('translate-interface')->withoutOverlapping();
// A link whose page could not be read (site down, or it turned this server away) is tried again.
Schedule::command('posts:link-previews --days=2')->hourly()->withoutOverlapping(10);
// The login journal keeps a year.
Schedule::command('model:prune', ['--model' => [LoginLog::class]])->daily();
// Moments leave their authors' archive a few days after they disappear; their files go with them.
Schedule::command('model:prune', ['--model' => [Moment::class]])->hourly()->name('prune-moments')->withoutOverlapping(10);
// Days since joining count towards a level: once a night every member's level is counted again.
Schedule::call(fn () => Badges::refreshAll())->dailyAt('03:30')->name('badge-levels')->withoutOverlapping(30);
