<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Interface styles a member can switch between. One set of pages for all of them: a style changes
 * colours, roundness, density and type size (resources/css/app.css, [data-skin=…]) and, in the feed,
 * the top bar and the profile, how a few parts are laid out (the stream: and showcase: variants).
 */
class Skins
{
    /** In the order they are offered. */
    public const ALL = ['club', 'community', 'large', 'stream', 'showcase', 'forum'];

    public const COOKIE = 'skin';

    /** Signing in, signing up and the password pages always look like the club itself, whatever style is chosen. */
    public const CLUB_ONLY_ROUTES = ['login', 'register', 'password.request', 'password.reset', 'verification.notice', 'password.confirm'];

    /** @return list<string> the styles members may choose, set in the admin; the default is always among them */
    public static function enabled(): array
    {
        $saved = json_decode(Setting::get('skins_enabled') ?? 'null', true);
        $on = is_array($saved) ? array_values(array_intersect(self::ALL, $saved)) : self::ALL;

        return array_values(array_intersect(self::ALL, array_unique([...$on, self::default()])));
    }

    public static function default(): string
    {
        $skin = Setting::get('skin_default');

        return in_array($skin, self::ALL, true) ? $skin : 'club';
    }

    /** 'club' on the sign-in pages; elsewhere the member's choice, else the guest's (a cookie), else the site's default. */
    public static function current(?Request $request = null): string
    {
        $request ??= request();
        if ($request->routeIs(...self::CLUB_ONLY_ROUTES)) {
            return 'club';
        }
        $enabled = self::enabled();
        foreach ([$request->user()?->ui_skin, $request->cookie(self::COOKIE)] as $choice) {
            if (is_string($choice) && in_array($choice, $enabled, true)) {
                return $choice;
            }
        }

        return self::default();
    }
}
