<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\FriendList;
use App\Models\User;
use App\Notifications\ClubActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FriendController extends Controller
{
    /** Friends home, like Facebook's: requests waiting for an answer, then people you may know. */
    public function index(Request $request): Response
    {
        $me = $request->user();

        return Inertia::render('friends/index', [
            'menu' => self::menu($me),
            'requests' => self::withMutuals($me, $me->friendRequesters()->where('blocked', false)->latest('id')->get()),
            'suggestions' => self::withMutuals($me, self::suggestionsFor($me, 60)),
        ]);
    }

    /** Friend requests on the left, the chosen person's profile on the right. */
    public function requests(Request $request): Response
    {
        $me = $request->user();
        $sentIds = DB::table('friendships')->where('user_id', $me->id)->where('accepted', false)->pluck('friend_id');

        return $this->browse($request, 'requests', fn () => self::withMutuals($me, $me->friendRequesters()->where('blocked', false)->latest('id')->get()), [
            'sent' => fn () => self::withMutuals($me, User::query()->whereIn('id', $sentIds)->where('blocked', false)->whereNotIn('id', $me->hiddenUserIds())->get()),
        ]);
    }

    public function suggestions(Request $request): Response
    {
        $me = $request->user();

        return $this->browse($request, 'suggestions', fn () => self::withMutuals($me, self::suggestionsFor($me, 60)));
    }

    public function all(Request $request): Response
    {
        $me = $request->user();

        return $this->browse($request, 'all', fn () => self::withMutuals($me,
            $me->friends()->where('blocked', false)->whereNotIn('id', $me->hiddenUserIds())->onlineFirst()->orderBy('name')->get()));
    }

    /** Friends whose birthday they share with friends, nearest first. */
    public function birthdays(Request $request): Response
    {
        $me = $request->user();
        $today = now()->startOfDay();

        $people = $me->friends()->where('blocked', false)->whereNotNull('birthdate')->whereNotIn('id', $me->hiddenUserIds())->get()
            ->filter(fn (User $u) => $u->shows('birthdate', $me))
            ->map(function (User $u) use ($today) {
                $born = $u->birthdate;
                // A 29 February birthday falls on 1 March in other years.
                $next = Carbon::create($today->year, $born->month, 1)->addDays($born->day - 1);
                if ($next->lt($today)) {
                    $next = Carbon::create($today->year + 1, $born->month, 1)->addDays($born->day - 1);
                }

                return PeopleController::card($u) + [
                    'birthday' => $next->format('Y-m-d'),
                    'age' => $next->year - $born->year,
                    'days' => (int) $today->diffInDays($next),
                ];
            })
            ->sortBy([['days', 'asc'], ['name', 'asc']])
            ->values();

        return Inertia::render('friends/birthdays', ['menu' => self::menu($me), 'people' => $people]);
    }

    /** The member's own lists, and the friends in the chosen one. */
    public function lists(Request $request): Response
    {
        $me = $request->user();
        $friendIds = $me->friendIds();
        $lists = FriendList::query()->where('user_id', $me->id)->orderBy('name')
            ->with(['members' => fn ($q) => $q->whereIn('users.id', $friendIds)->where('blocked', false)->orderBy('name')])
            ->get();
        $current = $lists->firstWhere('id', (int) $request->query('list')) ?? $lists->first();

        return Inertia::render('friends/lists', [
            'menu' => self::menu($me),
            'lists' => $lists->map(fn (FriendList $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'count' => $l->members->count(),
                'people' => $l->members->take(3)->map(fn (User $u) => PeopleController::card($u))->values(),
            ]),
            'current' => $current ? [
                'id' => $current->id,
                'name' => $current->name,
                'members' => $current->members->map(fn (User $u) => PeopleController::card($u))->values(),
                'others' => $me->friends()->where('blocked', false)->whereNotIn('id', $me->hiddenUserIds())
                    ->whereNotIn('id', $current->members->pluck('id'))->orderBy('name')->get()
                    ->map(fn (User $u) => PeopleController::card($u)),
            ] : null,
        ]);
    }

    public function storeList(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $list = FriendList::create(['user_id' => $request->user()->id, 'name' => trim($data['name'])]);

        return redirect()->route('friends.lists', ['list' => $list->id]);
    }

    public function updateList(Request $request, FriendList $friendList): RedirectResponse
    {
        abort_unless($friendList->user_id === $request->user()->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $friendList->update(['name' => trim($data['name'])]);

        return back();
    }

    public function destroyList(Request $request, FriendList $friendList): RedirectResponse
    {
        abort_unless($friendList->user_id === $request->user()->id, 404);
        $friendList->delete();

        return redirect()->route('friends.lists');
    }

    public function addToList(Request $request, FriendList $friendList, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless($friendList->user_id === $me->id, 404);
        abort_unless($me->friendshipWith($user) === 'friends', 422);
        $friendList->members()->syncWithoutDetaching([$user->id]);

        return back();
    }

    public function removeFromList(Request $request, FriendList $friendList, User $user): RedirectResponse
    {
        abort_unless($friendList->user_id === $request->user()->id, 404);
        $friendList->members()->detach($user->id);

        return back();
    }

    /** "Remove" on a suggestion: that person is no longer suggested to this member. */
    public function dismiss(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user), 422);
        DB::table('friend_suggestion_dismissals')->insertOrIgnore(['user_id' => $me->id, 'dismissed_id' => $user->id, 'created_at' => now()]);

        return back();
    }

    /** A list page: people on the left, the chosen person's profile on the right (?id=). */
    private function browse(Request $request, string $kind, \Closure $people, array $extra = []): Response
    {
        $me = $request->user();

        return Inertia::render('friends/browse', [
            'kind' => $kind,
            'menu' => fn () => self::menu($me),
            'people' => $people,
            'preview' => fn () => $request->integer('id') ? self::preview($me, $request->integer('id')) : null,
        ] + $extra);
    }

    /** Numbers for the friends menu. */
    public static function menu(User $me): array
    {
        return [
            'requests' => $me->friendRequesters()->where('blocked', false)->count(),
            'friends' => $me->friendIds()->count(),
            'lists' => FriendList::query()->where('user_id', $me->id)->count(),
        ];
    }

    /**
     * People you may know: friends of friends with the most friends in common first,
     * then members recently on the site the viewer has no tie to yet.
     */
    public static function suggestionsFor(User $me, int $limit): Collection
    {
        $mine = $me->friendIds();
        $exclude = $mine
            ->merge(DB::table('friendships')->where('user_id', $me->id)->pluck('friend_id'))
            ->merge(DB::table('friendships')->where('friend_id', $me->id)->pluck('user_id'))
            ->merge($me->hiddenUserIds())
            ->merge(DB::table('friend_suggestion_dismissals')->where('user_id', $me->id)->pluck('dismissed_id'))
            ->push($me->id)
            ->unique()->values();

        $ranked = collect();
        if ($mine->isNotEmpty()) {
            $skip = $exclude->flip();
            $ranked = DB::table('friendships')->where('accepted', true)
                ->where(fn ($q) => $q->whereIn('user_id', $mine)->orWhereIn('friend_id', $mine))
                ->get(['user_id', 'friend_id'])
                ->flatMap(fn ($row) => [$row->user_id, $row->friend_id])
                ->reject(fn ($id) => $skip->has($id))
                ->countBy()
                ->sortDesc();
        }

        $known = User::query()->whereIn('id', $ranked->keys())->where('blocked', false)->listedFor($me)->get()
            ->sortByDesc(fn (User $u) => $ranked[$u->id] ?? 0)->take($limit)->values();

        if ($known->count() < $limit) {
            $known = $known->merge(User::query()
                ->where('blocked', false)
                ->listedFor($me)
                ->whereNotIn('id', $exclude->merge($known->pluck('id')))
                ->showable()
                ->latestSeen()
                ->limit($limit - $known->count())
                ->get());
        }

        return $known;
    }

    /** Cards with the friends each person has in common with the viewer (count and a few faces). */
    public static function withMutuals(User $me, Collection $users): Collection
    {
        $mutuals = self::mutualIds($me, $users->pluck('id'));
        $faces = User::query()->whereIn('id', collect($mutuals)->flatMap(fn ($ids) => $ids->take(2))->unique())->get()->keyBy('id');

        return $users->map(fn (User $u) => PeopleController::card($u) + [
            'mutual' => isset($mutuals[$u->id]) ? $mutuals[$u->id]->count() : 0,
            'mutual_people' => isset($mutuals[$u->id])
                ? $mutuals[$u->id]->take(2)->map(fn ($id) => $faces->get($id))->filter()->map(fn (User $f) => PeopleController::card($f))->values()
                : [],
        ])->values();
    }

    /** @return array<int, Collection> person id => ids of the viewer's friends who are also theirs */
    private static function mutualIds(User $me, Collection $ids): array
    {
        $mine = $me->friendIds()->diff($me->hiddenUserIds())->values();
        if ($ids->isEmpty() || $mine->isEmpty()) {
            return [];
        }

        $map = [];
        DB::table('friendships')->where('accepted', true)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereIn('user_id', $ids)->whereIn('friend_id', $mine))
                ->orWhere(fn ($q) => $q->whereIn('friend_id', $ids)->whereIn('user_id', $mine)))
            ->get(['user_id', 'friend_id'])
            ->each(function ($row) use (&$map, $ids, $mine) {
                if ($ids->contains($row->user_id) && $mine->contains($row->friend_id)) {
                    $map[$row->user_id][] = $row->friend_id;
                }
                if ($ids->contains($row->friend_id) && $mine->contains($row->user_id)) {
                    $map[$row->friend_id][] = $row->user_id;
                }
            });

        return array_map(fn ($list) => collect($list)->unique()->values(), $map);
    }

    /** A short profile for the right side of the list pages. */
    private static function preview(User $me, int $id): ?array
    {
        $user = User::query()->find($id);
        if (! $user || $user->blocked || $me->is($user) || $me->isBlockedWith($user)) {
            return null;
        }
        $card = self::withMutuals($me, collect([$user]))->first();
        $mutualIds = self::mutualIds($me, collect([$user->id]))[$user->id] ?? collect();

        return array_merge($card, [
            'cover' => $user->cover,
            'status' => $user->status,
            'about' => $user->about,
            'location' => $user->shows('location', $me) ? $user->location : null,
            'joined_at' => $user->created_at?->toIso8601String(),
            'friends_count' => $user->shows('friends', $me) ? $user->friends()->where('blocked', false)->count() : null,
            'mutual_people' => User::query()->whereIn('id', $mutualIds->take(8))->get()->map(fn (User $u) => PeopleController::card($u))->values(),
            'friendship' => $me->friendshipWith($user),
            'can_message' => $me->canMessage($user),
        ]);
    }

    /** The viewer's friends, for picking who sees a post. */
    public function list(Request $request): JsonResponse
    {
        return response()->json($request->user()->friends()->where('blocked', false)->orderBy('name')->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar])->values());
    }

    /** Asks $user to be friends, or accepts when $user already asked. */
    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user) || $user->blocked || $me->isBlockedWith($user), 422);

        $url = route('people.show', $me);
        switch ($me->friendshipWith($user)) {
            case 'none':
                DB::table('friendships')->insert(['user_id' => $me->id, 'friend_id' => $user->id, 'accepted' => false, 'created_at' => now(), 'updated_at' => now()]);
                ClubActivity::send($user, 'friend_request', $me, route('friends.index'), 'user:'.$user->id);
                break;
            case 'received':
                DB::table('friendships')->where('user_id', $user->id)->where('friend_id', $me->id)->update(['accepted' => true, 'updated_at' => now()]);
                ClubActivity::retract($me, 'friend_request', $user, 'user:'.$me->id);
                ClubActivity::send($user, 'friend_accepted', $me, $url, 'user:'.$user->id);
                // Friends talk freely: a chat request still waiting between them is let in, so calls open at once.
                $chat = Conversation::query()
                    ->whereHas('users', fn ($q) => $q->whereKey($me->id))
                    ->whereHas('users', fn ($q) => $q->whereKey($user->id))
                    ->has('users', '=', 2)
                    ->first();
                $chat?->users()->wherePivotNull('accepted_at')->get()
                    ->each(fn (User $u) => $chat->users()->updateExistingPivot($u->id, ['accepted_at' => now(), 'declined_at' => null]));
                break;
        }

        return back();
    }

    /** Cancels a request, declines one, or ends a friendship. */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        DB::table('friendships')
            ->where(fn ($q) => $q->where('user_id', $me->id)->where('friend_id', $user->id))
            ->orWhere(fn ($q) => $q->where('user_id', $user->id)->where('friend_id', $me->id))
            ->delete();
        // Former friends leave each other's own lists.
        DB::table('friend_list_user')
            ->where(fn ($q) => $q->where('user_id', $user->id)->whereIn('friend_list_id', FriendList::query()->where('user_id', $me->id)->select('id')))
            ->orWhere(fn ($q) => $q->where('user_id', $me->id)->whereIn('friend_list_id', FriendList::query()->where('user_id', $user->id)->select('id')))
            ->delete();
        ClubActivity::retract($user, 'friend_request', $me, 'user:'.$user->id);

        return back();
    }
}
