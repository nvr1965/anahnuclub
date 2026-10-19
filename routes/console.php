<?php

use Illuminate\Support\Facades\Schedule;

// Shared hosting has no long-running workers: cron runs the scheduler every minute
// and the scheduler drains the queue (pre-translation of new posts and comments).
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
// Letters get their own worker, so a long translation run never holds them up.
Schedule::command('queue:work --queue=mail --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
