<?php

namespace App\Http\Middleware;

use App\Support\SiteMeta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The site answers at one address only (APP_URL, https://anahnu.club). The same page at another
 * one (www.anahnu.club, the hosting's own name) is sent there for good (301): otherwise its pages
 * name that other address as their own, and search engines count the two as copies of each other.
 * Only on the real site; a test copy keeps answering wherever it is opened.
 */
class MainAddress
{
    public function handle(Request $request, Closure $next): Response
    {
        $main = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (SiteMeta::indexable() && $main && $request->isMethodSafe() && strcasecmp($request->getHost(), $main) !== 0) {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
