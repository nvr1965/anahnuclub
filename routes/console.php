<?php

use App\Jobs\TranslateInterface;
use Illuminate\Support\Facades\Schedule;

// Shared hosting has no long-running workers: cron runs the scheduler every minute
// and the scheduler drains the queue (pre-translation of new posts and comments).
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
// Interface translation of an added language stops when the services hit their daily limits; pick it up again.
Schedule::call([TranslateInterface::class, 'resumeAll'])->hourly()->name('translate-interface')->withoutOverlapping();
// Letters get their own worker, so a long translation run never holds them up.
Schedule::command('queue:work --queue=mail --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
