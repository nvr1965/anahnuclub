<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\SocialLoginController;
use App\Models\Language;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Skins;
use App\Translation\InterfaceStrings;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $languages = Language::enabled();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => $locale,
            'dir' => $languages->firstWhere('code', $locale)?->rtl ? 'rtl' : 'ltr',
            'languages' => $languages->map->only('code', 'native_name', 'rtl')->values(),
            // The interface style (App\Support\Skins) and the ones on offer.
            'skin' => fn () => Skins::current($request),
            'skins' => fn () => Skins::enabled(),
            // About 40 KB of interface text. The page keeps it after the first load and says which version
            // it has (X-UI-Strings); moving on to the next page then sends it again only when it changed.
            'translations' => fn () => $request->header('X-Inertia') && $request->header('X-UI-Strings') === $this->uiStringsVersion($locale)
                ? null
                : $this->uiStrings($locale),
            'translations_version' => fn () => $this->uiStringsVersion($locale),
            // Links at the bottom of every page.
            'footer' => fn () => [
                'pages' => Page::query()->where('published', true)->where('in_footer', true)->orderBy('sort')->orderBy('id')->get()
                    ->map(fn (Page $p) => ['slug' => $p->slug, 'title' => $p->in($locale)['title']])->values(),
                'donate' => Setting::donateLinks(),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            // Sign-in services with keys set, for the buttons on the login and sign-up forms.
            'social' => fn () => $request->user() ? [] : SocialLoginController::enabled(),
            // Badges in the top bar; the page asks for just these again now and then.
            'counts' => fn () => $request->user() ? self::counts($request->user()) : null,
        ]);
    }

    /**
     * The numbers on the top bar's badges. An open tab asks for them every half minute at /counts, which
     * counts just these, so the page it shows (the feed, a group) is not built again each time.
     *
     * @return array{messages: int, friend_requests: int, notifications: int}
     */
    public static function counts(User $user): array
    {
        return [
            'messages' => $user->unreadMessagesCount(),
            'friend_requests' => $user->friendRequesters()->where('blocked', false)->count(),
            'notifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * A page and its JSON (what the page asks for when it moves on or refreshes itself) share one
     * address. The JSON is never stored, so a phone that restores a tab or goes back can only get
     * the page from the browser cache, never the raw data.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($request->header('X-Inertia')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /** @var array<string, array<string, string>> */
    private array $uiStrings = [];

    /** @return array<string, string> */
    private function uiStrings(string $locale): array
    {
        return $this->uiStrings[$locale] ??= array_merge(InterfaceStrings::read(config('app.fallback_locale')), InterfaceStrings::read($locale));
    }

    /** Changes whenever any interface text of this language changes (an update, an administrator's correction). */
    private function uiStringsVersion(string $locale): string
    {
        return $locale.'-'.substr(md5(json_encode($this->uiStrings($locale))), 0, 12);
    }
}
