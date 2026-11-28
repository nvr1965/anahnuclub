<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Whether a queue has a job to do now. The cron starts a queue worker only then: each worker is a new PHP
 * process that loads the whole site, which on shared hosting costs about as much CPU as a page, and most
 * minutes the queues are empty.
 */
class QueueBacklog
{
    /** @param  list<string>  $queues */
    public static function ready(array $queues): bool
    {
        $name = config('queue.default');
        $config = config("queue.connections.$name");
        // Other drivers have no cheap way to look; their worker runs as before.
        if (($config['driver'] ?? null) !== 'database') {
            return true;
        }

        $now = now()->getTimestamp();
        try {
            return DB::connection($config['connection'] ?? null)->table($config['table'] ?? 'jobs')
                ->whereIn('queue', $queues)
                ->where('available_at', '<=', $now)
                // A job a killed worker left reserved is free again after retry_after, as the worker sees it.
                ->where(fn ($q) => $q->whereNull('reserved_at')->orWhere('reserved_at', '<=', $now - (int) ($config['retry_after'] ?? 90)))
                ->exists();
        } catch (\Throwable) {
            return true;
        }
    }
}
