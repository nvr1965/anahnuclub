<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Notifications\ClubActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Members shutting out another member or a whole group, and the settings page that lists them. */
class BlockController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/blocked', [
            'people' => User::query()
                ->whereIn('id', DB::table('user_blocks')->where('user_id', $user->id)->select('blocked_id'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar]),
            'messenger' => User::query()
                ->whereIn('id', DB::table('message_blocks')->where('user_id', $user->id)->select('blocked_id'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar]),
            'messages_from' => $user->messages_from,
            'groups' => Group::query()
                ->whereIn('id', $user->blockedGroupIds())
                ->orderBy('name')
                ->get()
                ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name, 'slug' => $g->slug]),
        ]);
    }

    /** Blocking ends any friendship or pending request and takes back the notices between the two. */
    public function blockUser(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user), 422);

        DB::table('user_blocks')->insertOrIgnore(['user_id' => $me->id, 'blocked_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('friendships')
            ->where(fn ($q) => $q->where('user_id', $me->id)->where('friend_id', $user->id))
            ->orWhere(fn ($q) => $q->where('user_id', $user->id)->where('friend_id', $me->id))
            ->delete();
        ClubActivity::retract($me, 'friend_request', $user, 'user:'.$me->id);
        ClubActivity::retract($user, 'friend_request', $me, 'user:'.$user->id);
        $me->unreadNotifications()->where('data->actor->id', $user->id)->delete();
        $me->forgetBlocks();

        return back();
    }

    public function unblockUser(Request $request, User $user): RedirectResponse
    {
        DB::table('user_blocks')->where('user_id', $request->user()->id)->where('blocked_id', $user->id)->delete();
        $request->user()->forgetBlocks();

        return back();
    }

    /** Shuts the messenger only: profiles, posts and friendship stay as they are. */
    public function blockMessages(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user), 422);

        DB::table('message_blocks')->insertOrIgnore(['user_id' => $me->id, 'blocked_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $me->forgetBlocks();

        return back();
    }

    public function unblockMessages(Request $request, User $user): RedirectResponse
    {
        DB::table('message_blocks')->where('user_id', $request->user()->id)->where('blocked_id', $user->id)->delete();
        $request->user()->forgetBlocks();

        return back();
    }

    /** Who may start a chat: everyone (non-friends land in requests) or friends only. */
    public function messagesFrom(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['messages_from' => ['required', 'in:everyone,friends']]));

        return back();
    }

    /** Blocking a group also leaves it. */
    public function blockGroup(Request $request, Group $group): RedirectResponse
    {
        $me = $request->user();
        DB::table('group_blocks')->insertOrIgnore(['user_id' => $me->id, 'group_id' => $group->id, 'created_at' => now(), 'updated_at' => now()]);
        $group->members()->detach($me->id);
        $me->forgetBlocks();

        return back();
    }

    public function unblockGroup(Request $request, Group $group): RedirectResponse
    {
        DB::table('group_blocks')->where('user_id', $request->user()->id)->where('group_id', $group->id)->delete();
        $request->user()->forgetBlocks();

        return back();
    }
}
