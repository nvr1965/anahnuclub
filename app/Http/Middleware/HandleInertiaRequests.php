<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\SocialLoginController;
use App\Models\Language;
use App\Models\Page;
use App\Models\Setting;
use App\Translation\InterfaceStrings;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            'translations' => fn () => $this->uiStrings($locale),
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
            'counts' => fn () => $request->user() ? [
                'messages' => $request->user()->unreadMessagesCount(),
                'friend_requests' => $request->user()->friendRequesters()->where('blocked', false)->count(),
                'notifications' => $request->user()->unreadNotifications()->count(),
            ] : null,
        ]);
    }

    /** @return array<string, string> */
    private function uiStrings(string $locale): array
    {
        return array_merge(InterfaceStrings::read(config('app.fallback_locale')), InterfaceStrings::read($locale));
    }
}
