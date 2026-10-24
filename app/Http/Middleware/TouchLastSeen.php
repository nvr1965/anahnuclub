<?php

namespace App\Http\Middleware;

use App\Models\LoginLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Remembers when a member was last here, for "online" marks and ordering people lists.
 * A member coming back after an hour away while still signed in (sessions last 30 days)
 * goes into the login journal as a return visit, or the journal would show almost nobody.
 */
class TouchLastSeen
{
    public const RETURN_AFTER_MINUTES = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(2)))) {
            if ($user->last_seen_at?->lt(now()->subMinutes(self::RETURN_AFTER_MINUTES))) {
                try {
                    LoginLog::record($request, $user, 'session', true);
                } catch (Throwable $e) {
                    report($e);
                }
            }
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
