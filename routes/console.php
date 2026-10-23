<?php

use App\Jobs\TranslateInterface;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Shared hosting has no long-running workers: cron runs the scheduler every minute
// and the scheduler drains the queue (pre-translation of new posts and comments).
// Tasks run one after another, so the letters come first: a long translation run never holds them up.
// The overlap locks expire after 10 minutes: a worker the host killed must not stop letters for a day.
Schedule::call(fn () => Cache::forever('schedule:last-run', now()->getTimestamp()))->everyMinute()->name('heartbeat');
Schedule::command('queue:work --queue=mail --stop-when-empty --max-time=50 --tries=3 --backoff=60')->everyMinute()->withoutOverlapping(10);
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(10);
// Interface translation of an added language stops when the services hit their daily limits; pick it up again.
Schedule::call([TranslateInterface::class, 'resumeAll'])->hourly()->name('translate-interface')->withoutOverlapping();
// A link whose page could not be read (site down, or it turned this server away) is tried again.
Schedule::command('posts:link-previews --days=2')->hourly()->withoutOverlapping(10);
// The login journal keeps a year.
Schedule::command('model:prune', ['--model' => [LoginLog::class]])->daily();
