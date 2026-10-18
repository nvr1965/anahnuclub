<?php

namespace App\Http\Middleware;

use App\Models\Language;
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
            'auth' => [
                'user' => $request->user(),
            ],
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
        $read = fn (string $code) => is_file($path = lang_path($code.'.json'))
            ? json_decode(file_get_contents($path), true) ?? []
            : [];

        return array_merge($read(config('app.fallback_locale')), $read($locale));
    }
}
