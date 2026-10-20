<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Language;
use App\Models\Photo;
use App\Models\User;
use App\Support\ImageStore;
use App\Translation\ContentTranslator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Member profiles: cover, picture, about, friends, photos and the member's own posts. */
class PeopleController extends Controller
{
    public const TABS = ['posts', 'about', 'friends', 'photos', 'videos'];

    /** Every member the viewer can see, recently active first, with a search by name and filters by country and language. */
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $q = trim((string) $request->query('q'));
        $op = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $country = strtoupper((string) $request->query('country'));
        $lang = (string) $request->query('lang');

        $visible = fn () => User::query()
            ->where('blocked', false)
            ->whereKeyNot($viewer->id)
            ->listedFor($viewer)
            ->whereNotIn('id', $viewer->hiddenUserIds());
        // Only countries their owners show to everyone count for the filter.
        $publicCountry = fn (Builder $b) => $b->whereNotNull('country')
            ->where(fn (Builder $w) => $w->whereNull('privacy->country')->orWhere('privacy->country', 'public'));

        $people = $visible()
            ->when(preg_match('/^[A-Z]{2}$/', $country), fn (Builder $b) => $publicCountry($b)->where('country', $country))
            ->when($lang !== '', fn (Builder $b) => $b->where('locale', $lang))
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('name', $op, '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('username', $op, '%'.addcslashes($q, '%_\\').'%')))
            ->latestSeen()
            ->orderBy('id')
            ->paginate(48)
            ->withQueryString();

        $ties = DB::table('friendships')
            ->where(fn ($w) => $w->where('user_id', $viewer->id)->whereIn('friend_id', $people->pluck('id')))
            ->orWhere(fn ($w) => $w->where('friend_id', $viewer->id)->whereIn('user_id', $people->pluck('id')))
            ->get();

        return Inertia::render('people/index', [
            'people' => $people->through(function (User $u) use ($ties, $viewer) {
                $row = $ties->first(fn ($t) => $t->user_id === $u->id || $t->friend_id === $u->id);

                return self::card($u) + ['friendship' => match (true) {
                    $row === null => 'none',
                    (bool) $row->accepted => 'friends',
                    $row->user_id === $viewer->id => 'sent',
                    default => 'received',
                }];
            }),
            'total' => $people->total(),
            'q' => $q,
            'country' => $country ?: null,
            'lang' => $lang ?: null,
            // What the filters can offer: the countries and languages members actually have, with how many.
            'countries' => $publicCountry($visible())->groupBy('country')->selectRaw('country, count(*) as n')->pluck('n', 'country'),
            'langs' => $visible()->whereNotNull('locale')->groupBy('locale')->selectRaw('locale, count(*) as n')->pluck('n', 'locale'),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();
        $me = $viewer->is($user);
        abort_if($user->blocked && ! $me && ! $viewer->is_admin, 404);
        // Someone who blocked the viewer is simply not there; someone the viewer blocked shows only a way back.
        $blockedByMe = ! $me && $viewer->hasBlocked($user);
        abort_if(! $me && ! $blockedByMe && ! $viewer->is_admin && $viewer->isBlockedWith($user), 404);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'posts';
        $friends = $user->friends()->where('blocked', false)->listedFor($viewer)->whereNotIn('id', $viewer->hiddenUserIds())->latestSeen();
        $photos = Photo::query()->where('user_id', $user->id)->whereNotNull('thumb_path')
            ->whereDoesntHave('album', fn (Builder $q) => $q->whereNotNull('group_id'))
            // Pictures of a post follow its audience.
            ->where(fn (Builder $q) => $q->whereNull('post_id')->orWhereHas('post', fn (Builder $p) => $p->visibleTo($viewer)))
            ->latest('id');

        $posts = null;
        if ($tab === 'posts' && ! $blockedByMe) {
            $posts = FeedController::postQuery($request)
                ->where('user_id', $user->id)
                // Posts in private groups stay inside those groups.
                ->when(! $me, fn (Builder $q) => $q->whereDoesntHave('group', fn (Builder $g) => $g->where('is_private', true)))
                ->paginate(20)
                ->withQueryString();
        }

        return Inertia::render('people/show', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'cover' => $user->cover,
                'status' => $user->status,
                'about' => $user->about,
                // Details are left out for viewers their audience does not include.
                'location' => $user->shows('location', $viewer) ? $user->location : null,
                'location_translated' => $user->shows('location', $viewer) ? app(ContentTranslator::class)->translate($user, 'location', app()->getLocale()) : null,
                'country' => $user->shows('country', $viewer) ? $user->country : null,
                'website' => $user->shows('website', $viewer) ? $user->website : null,
                'birthdate' => $user->shows('birthdate', $viewer) ? $user->birthdate?->format('Y-m-d') : null,
                'gender' => $user->shows('gender', $viewer) ? $user->gender : null,
                'privacy' => $me ? array_merge(array_fill_keys(User::PRIVATE_FIELDS, 'public'), $user->privacy ?? []) : null,
                'friends_hidden' => ! $user->shows('friends', $viewer),
                'joined_at' => $user->created_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'online' => $user->isOnline(),
                'friends_count' => $user->shows('friends', $viewer) ? (clone $friends)->count() : null,
                'posts_count' => $user->posts()->visibleTo($viewer)->count(),
                'is_me' => $me,
                'locale' => $me ? $user->locale : null,
                'friendship' => $me ? null : $viewer->friendshipWith($user),
                'blocked_by_me' => $blockedByMe,
                'messages_blocked_by_me' => ! $me && $viewer->hasMessengerBlocked($user),
                // Shut when they take messages from friends only, or the messenger is blocked either way.
                'can_message' => ! $me && $viewer->canMessage($user),
            ],
            'tab' => $tab,
            'posts' => $posts ? PostResource::collection($posts) : null,
            'friends' => $blockedByMe || ! $user->shows('friends', $viewer) ? [] : (clone $friends)->limit($tab === 'friends' ? 500 : 9)->get()->map(fn (User $u) => self::card($u)),
            'photos' => $blockedByMe ? [] : (clone $photos)->limit($tab === 'photos' ? 120 : 9)->get()
                ->map(fn (Photo $p) => $p->card() + ['album_id' => $p->album_id]),
            'albums' => $tab === 'photos' && ! $blockedByMe ? AlbumController::listFor($user, $me) : [],
            'videos' => $tab === 'videos' && ! $blockedByMe ? VideoController::visible($request)->where('user_id', $user->id)->latest()->limit(60)->get()->map->card() : [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:500'],
            'about' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'privacy' => ['nullable', 'array'],
            'privacy.*' => ['in:public,friends,only_me'],
            'locale' => ['sometimes', 'string', Rule::in(Language::enabled()->pluck('code')->all())],
        ]);
        if (isset($data['privacy'])) {
            $data['privacy'] = array_intersect_key($data['privacy'], array_flip(User::PRIVATE_FIELDS));
        }

        if (isset($data['locale'])) {
            // The default language: the interface switches to it now and on other devices.
            $data['ui_locale'] = $data['locale'];
            $request->session()->put('locale', $data['locale']);
        }
        $request->user()->forceFill($data)->save();

        return back();
    }

    public function avatar(Request $request): RedirectResponse
    {
        return $this->picture($request, 'avatar_path', 'avatars', 400, 400);
    }

    public function cover(Request $request): RedirectResponse
    {
        return $this->picture($request, 'cover_path', 'covers', 1640, 624);
    }

    private function picture(Request $request, string $column, string $folder, int $width, int $height): RedirectResponse
    {
        $user = $request->user();

        if ($request->boolean('remove')) {
            ImageStore::delete($user->{$column});
            $user->update([$column => null]);

            return back();
        }

        $request->validate(['image' => ['required', 'image', 'max:10240']]);
        $old = $user->{$column};
        $user->update([$column => ImageStore::save($request->file('image'), $folder, $width, $height)]);
        ImageStore::delete($old);

        return back();
    }

    /** A member as shown in friend lists and suggestions. */
    public static function card(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->avatar, 'online' => $user->isOnline()];
    }
}
