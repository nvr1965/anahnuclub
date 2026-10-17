<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Interface language: the user's saved choice, then the session, then the browser. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $codes = Language::enabled()->pluck('code')->all();

        $locale = collect([
            $request->user()?->locale,
            $request->session()->get('locale'),
            $request->getPreferredLanguage($codes),
        ])->first(fn ($code) => in_array($code, $codes, true)) ?? config('app.locale');

        app()->setLocale($locale);

        return $next($request);
    }
}
