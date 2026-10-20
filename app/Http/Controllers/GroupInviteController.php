<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\User;
use App\Notifications\ClubActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Members of a group inviting other club members into it; the invited one joins or declines. */
class GroupInviteController extends Controller
{
    /** Club members to pick from: friends first, then whoever was here last; members of the group left out. */
    public function candidates(Request $request, Group $group): JsonResponse
    {
        $me = $request->user();
        abort_unless($group->roleOf($me), 403);
        $q = trim((string) $request->query('q'));
        $op = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $friendIds = $me->friendIds();
        $invited = $group->invitations()->pluck('user_id');

        $people = User::query()
            ->where('blocked', false)
            ->whereKeyNot($me->id)
            ->listedFor($me)
            ->whereNotIn('id', $me->hiddenUserIds())
            ->whereNotIn('id', $group->members()->select('users.id'))
            ->when($q !== '', fn (Builder $b) => $b->where('name', $op, '%'.addcslashes($q, '%_\\').'%'))
            ->when($friendIds->isNotEmpty(), fn (Builder $b) => $b->orderByRaw('case when users.id in ('.$friendIds->map(fn ($id) => (int) $id)->implode(',').') then 0 else 1 end'))
            ->latestSeen()
            ->limit(40)
            ->get();

        return response()->json($people->map(fn (User $u) => PeopleController::card($u) + [
            'friend' => $friendIds->contains($u->id),
            'invited' => $invited->contains($u->id),
        ])->values());
    }

    public function store(Request $request, Group $group): RedirectResponse
    {
        $me = $request->user();
        abort_unless($group->roleOf($me), 403);
        $ids = collect($request->validate(['user_ids' => ['required', 'array', 'max:50'], 'user_ids.*' => ['integer']])['user_ids']);

        User::whereIn('id', $ids)->where('blocked', false)
            ->whereNotIn('id', $group->members()->select('users.id'))
            ->get()
            ->reject(fn (User $u) => $u->is($me) || $me->isBlockedWith($u))
            ->each(function (User $u) use ($group, $me) {
                $group->invitations()->updateOrCreate(['user_id' => $u->id], ['from_id' => $me->id]);
                ClubActivity::send($u, 'group_invite', $me, route('groups.show', $group), 'group:'.$group->id, $group->name);
            });

        return back();
    }

    /** Joining from an invitation; into a closed group without waiting if someone running it invited. */
    public function accept(Request $request, Group $group, GroupInvitation $invitation): RedirectResponse
    {
        $me = $request->user();
        abort_unless($invitation->group_id === $group->id && $invitation->user_id === $me->id, 404);
        abort_if($me->blockedGroupIds()->contains($group->id), 422);
        $approved = ! $group->is_private || $group->canModerate($invitation->from);
        $group->members()->syncWithoutDetaching([$me->id => ['approved' => $approved]]);
        $invitation->delete();
        Group::forgetRoles();
        ClubActivity::retract($me, 'group_invite', $invitation->from, 'group:'.$group->id);

        return back();
    }

    /** Declined by the invited one, or taken back by whoever sent it or by those running the group. */
    public function decline(Request $request, Group $group, GroupInvitation $invitation): RedirectResponse
    {
        $me = $request->user();
        abort_unless($invitation->group_id === $group->id, 404);
        abort_unless(in_array($me->id, [$invitation->user_id, $invitation->from_id], true) || in_array($group->roleOf($me), ['owner', 'admin'], true), 403);
        ClubActivity::retract($invitation->user, 'group_invite', $invitation->from, 'group:'.$group->id);
        $invitation->delete();

        return back();
    }
}
