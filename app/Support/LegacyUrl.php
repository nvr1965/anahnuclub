<?php

namespace App\Support;

use App\Models\Album;
use App\Models\Event;
use App\Models\Group;
use App\Models\Language;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Addresses of the old Joomla + JomSocial site, which Google and old links still point to, mapped to
 * the same thing here. The import kept the old ids, so the id in the old address finds the new page.
 *
 *   /community/groups/viewgroup/5-name.html             group 5
 *   /community/groups/viewdiscussion/7-title.html       the post made from discussion 7 (viewbulletin: announcement)
 *   /community/events/viewevent/3-title.html            event 3
 *   /community/videos/video/9-title.html                the video imported from video 9
 *   /community/12-ivan/profile.html, ?userid=12          member 12
 *   ?actid=55                                           the post made from stream entry 55
 *   index.php?option=com_community&view=groups&task=viewgroup&groupid=5   the same, without friendly URLs
 */
class LegacyUrl
{
    /** Where an old address now lives; null if nothing here matches it. */
    public static function find(Request $request): ?string
    {
        $segments = self::segments($request);
        // A language prefix (/ru/..., /he/...) becomes the page's language.
        $lang = isset($segments[0]) && Language::isEnabled($segments[0]) ? array_shift($segments) : null;
        // The old site's home page in a language (/ru, /he/) is the home page here in that language.
        $url = $lang && $segments === [] && $request->query('option') === null
            ? route('home').'/'
            : self::target($segments, $request->query());

        return $url && $lang ? SiteMeta::in($url, $lang) : $url;
    }

    /** Whether the address is one of the old site's at all (then it is gone, 410, if nothing matches). */
    public static function isLegacy(Request $request): bool
    {
        $segments = self::segments($request);
        // Every page of the old site in a language began with it (/ru/..., /he/...); none here do.
        if (isset($segments[0]) && Language::isEnabled($segments[0])) {
            return true;
        }

        return preg_match('~\.(html?|feed)$~i', $request->path())
            || $request->query('option') !== null || $request->query('Itemid') !== null
            || array_intersect($segments, ['community', 'component', 'index.php', 'jomsocial']) !== [];
    }

    /** Old RSS feeds (events.feed, ?format=feed): there are none here, so they are gone for good. */
    public static function isFeed(Request $request): bool
    {
        return preg_match('~\.feed$~i', $request->path()) || $request->query('format') === 'feed';
    }

    /** @return list<string> lower-case path parts, without the .html or .feed ending */
    private static function segments(Request $request): array
    {
        $path = mb_strtolower(preg_replace('~\.(html?|feed)$~i', '', trim(rawurldecode($request->path()), '/')));

        return $path === '' ? [] : explode('/', $path);
    }

    private static function target(array $segments, array $query): ?string
    {
        // The id comes from the query (…&groupid=5) or from the part after the task (viewgroup/5-name).
        $id = function (string $name, string $task) use ($segments, $query): int {
            if (is_scalar($query[$name] ?? null) && (int) $query[$name] > 0) {
                return (int) $query[$name];
            }
            $at = array_search($task, $segments, true);

            return $at !== false ? self::leadingId($segments[$at + 1] ?? '') : 0;
        };
        $param = fn (string $name) => is_scalar($query[$name] ?? null) ? (int) $query[$name] : 0;

        if ($n = $id('topicid', 'viewdiscussion')) {
            return self::post('discussion', $n);
        }
        if ($n = $id('bulletinid', 'viewbulletin')) {
            return self::post('bulletin', $n);
        }
        if ($n = $param('actid')) {
            return self::post('activity', $n);
        }
        if ($n = $id('eventid', 'viewevent')) {
            return Event::whereKey($n)->exists() ? route('events.show', $n) : null;
        }
        if ($n = $id('groupid', 'viewgroup')) {
            return ($group = Group::find($n)) ? route('groups.show', $group) : null;
        }
        if ($n = $id('videoid', 'video')) {
            $video = Video::where('legacy_source', 'jomsocial_video')->where('legacy_id', $n)->first();

            return $video ? route('videos.show', $video) : null;
        }
        if ($n = $param('albumid')) {
            return Album::whereKey($n)->exists() ? route('albums.show', $n) : null;
        }
        if ($user = self::member($segments, $param('userid'))) {
            return route('people.show', $user);
        }

        // Lists of the old site have their counterpart here.
        foreach (['groups' => 'groups.index', 'events' => 'events.index', 'videos' => 'videos.index', 'friends' => 'friends.index',
            'inbox' => 'messages.index', 'search' => 'search', 'register' => 'register', 'members' => 'people.index'] as $view => $name) {
            if (($query['view'] ?? null) === $view || in_array($view, $segments, true)) {
                return route($name);
            }
        }

        return null;
    }

    private static function post(string $source, int $legacyId): ?string
    {
        $id = Post::where('legacy_source', $source)->where('legacy_id', $legacyId)->value('id');

        return $id ? route('posts.show', $id) : null;
    }

    /**
     * A profile address: ?userid=12, or a part "12-ivan" (JomSocial's "id-username" alias). That part
     * only counts when the rest is the member's username or name, or it follows "profile" or
     * the old menu ("community", "jomsocial"): an old article "12-about-us" must not open member 12.
     */
    private static function member(array $segments, int $userId): ?User
    {
        if ($userId) {
            return User::find($userId);
        }
        foreach ($segments as $i => $segment) {
            if (! preg_match('~^(\d+)[-:](.+)$~', $segment, $m) || ! ($user = User::find((int) $m[1]))) {
                continue;
            }
            $named = in_array($m[2], [Str::slug($user->username ?? ''), Str::slug($user->name)], true);
            if ($named || in_array($segments[$i - 1] ?? null, ['profile', 'community', 'jomsocial'], true)) {
                return $user;
            }
        }

        return null;
    }

    /** 5 out of "5-name" or "5:name" (Joomla wrote both). */
    private static function leadingId(string $segment): int
    {
        return preg_match('~^(\d+)~', $segment, $m) ? (int) $m[1] : 0;
    }
}
