<?php

use Illuminate\Support\Facades\Schedule;

// Shared hosting has no long-running workers: cron runs the scheduler every minute
// and the scheduler drains the queue (pre-translation of new posts and comments).
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

// Translates whatever is still untranslated (old posts first in line: newest first), within the daily budget.
Schedule::command('translations:fill')->dailyAt('03:00')->withoutOverlapping();
