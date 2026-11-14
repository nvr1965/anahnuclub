<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Addresses the site's pages ask for data in the background (friends, calls, messages...).
 * Typed or pasted into the browser's address bar they are "not found", so nobody sees the raw data;
 * an admin still gets it there, to look into a problem.
 */
class FetchOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->opensInBrowser($request) && ! $request->user()?->is_admin, 404);

        return $next($request);
    }

    private function opensInBrowser(Request $request): bool
    {
        // Browsers say whether the address was opened as a page or asked for by a script.
        $mode = $request->header('Sec-Fetch-Mode');
        if ($mode !== null) {
            return $mode === 'navigate';
        }

        // An old browser without that header: a page opened in it asks for HTML, a script's fetch() does not.
        return ! $request->ajax() && str_contains((string) $request->header('Accept'), 'text/html');
    }
}
