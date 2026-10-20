<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ClubActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FriendController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $friendIds = $user->friendIds();
        $pendingIds = DB::table('friendships')->where('user_id', $user->id)->where('accepted', false)->pluck('friend_id');

        return Inertia::render('friends/index', [
            'requests' => $user->friendRequesters()->where('blocked', false)->latest('id')->get()->map(fn (User $u) => PeopleController::card($u)),
            'sent' => User::query()->whereIn('id', $pendingIds)->get()->map(fn (User $u) => PeopleController::card($u)),
            'friends' => $user->friends()->where('blocked', false)->onlineFirst()->orderBy('name')->get()->map(fn (User $u) => PeopleController::card($u)),
            // Members recently active the viewer has no tie to yet.
            'suggestions' => User::query()
                ->where('blocked', false)
                ->whereKeyNot($user->id)
                ->listedFor($user)
                ->whereNotIn('id', $friendIds->merge($pendingIds)->merge($user->friendRequesters()->pluck('id'))->merge($user->hiddenUserIds()))
                ->showable()
                ->latestSeen()
                ->limit(12)
                ->get()
                ->map(fn (User $u) => PeopleController::card($u)),
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
        ClubActivity::retract($user, 'friend_request', $me, 'user:'.$user->id);

        return back();
    }
}
