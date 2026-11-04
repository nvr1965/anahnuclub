<?php

namespace App\Support;

/**
 * Work that must not hold up the reply, such as translating a new post.
 * It runs right after the response where PHP can hand the page over first (PHP-FPM, LiteSpeed);
 * elsewhere the browser would wait for it, so it goes to the queue, which cron drains every minute.
 */
class Background
{
    public static function run(object $job): void
    {
        self::canFinishEarly() ? dispatch($job)->afterResponse() : dispatch($job);
    }

    /**
     * Work that cannot wait for cron, such as ringing a phone for a call: right after the response
     * where PHP can hand the page over first, otherwise during the request itself, never the queue.
     */
    public static function now(object $job): void
    {
        self::canFinishEarly() ? dispatch($job)->afterResponse() : dispatch_sync($job);
    }

    public static function canFinishEarly(): bool
    {
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }
}
