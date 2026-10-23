<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Translation\InterfaceStrings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Interface language: a choice made by hand (this visit, then saved for the member), otherwise the browser's. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $codes = Language::enabled()->pluck('code')->all();
        // An invitation link carries the inviter's language (?lang=he), so the guest lands in it.
        $asked = $request->query('lang');
        if (is_string($asked) && in_array($asked, $codes, true) && ! $request->user()) {
            $request->session()->put('locale', $asked);
        }

        $locale = collect([
            $request->session()->get('locale'),
            $request->user()?->ui_locale,
            $this->browser($request, $codes),
            $request->user()?->locale,
        ])->first(fn ($code) => in_array($code, $codes, true)) ?? config('app.locale');

        app()->setLocale($locale);
        // __() reads only lang/{code}.json; a language added in the admin keeps its texts in storage,
        // and hand-made corrections too, so pages rendered here (titles, link cards, mail) get them as well.
        app('translator')->addLines(collect(InterfaceStrings::read($locale))->mapWithKeys(fn ($text, $key) => ['*.'.$key => $text])->all(), $locale);

        return $next($request);
    }

    /** The first of the browser's languages the site has ("he-IL" counts as "he"); null if none. */
    private function browser(Request $request, array $codes): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $code = strtolower(strtok($language, '_-'));
            if (in_array($code, $codes, true)) {
                return $code;
            }
        }

        return null;
    }
}
