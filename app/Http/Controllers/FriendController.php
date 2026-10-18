<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'friends' => $user->friends()->where('blocked', false)->orderBy('name')->get()->map(fn (User $u) => PeopleController::card($u)),
            // Members recently active the viewer has no tie to yet.
            'suggestions' => User::query()
                ->where('blocked', false)
                ->whereKeyNot($user->id)
                ->whereNotIn('id', $friendIds->merge($pendingIds)->merge($user->friendRequesters()->pluck('id')))
                ->whereNotNull('avatar_path')
                ->orderByDesc('last_seen_at')
                ->limit(12)
                ->get()
                ->map(fn (User $u) => PeopleController::card($u)),
        ]);
    }

    /** Asks $user to be friends, or accepts when $user already asked. */
    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user) || $user->blocked, 422);

        match ($me->friendshipWith($user)) {
            'none' => DB::table('friendships')->insert(['user_id' => $me->id, 'friend_id' => $user->id, 'accepted' => false, 'created_at' => now(), 'updated_at' => now()]),
            'received' => DB::table('friendships')->where('user_id', $user->id)->where('friend_id', $me->id)->update(['accepted' => true, 'updated_at' => now()]),
            default => null,
        };

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

        return back();
    }
}
