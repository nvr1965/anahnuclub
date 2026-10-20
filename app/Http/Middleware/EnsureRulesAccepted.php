<?php

namespace App\Http\Middleware;

use App\Http\Controllers\WelcomeController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Members who have not accepted the current community rules see the welcome page first. */
class EnsureRulesAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Only pages: pictures, downloads and background requests carry on.
        $page = $request->isMethod('GET') && ($request->header('X-Inertia') || str_contains((string) $request->header('Accept'), 'text/html'));

        if ($user && $page && WelcomeController::mustAccept($user)) {
            return redirect()->guest(route('welcome'));
        }

        return $next($request);
    }
}
