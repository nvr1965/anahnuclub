<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupRoleOffer;
use App\Models\User;
use App\Notifications\ClubActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admins, moderators and handing a group over. Nobody gets a role without saying yes: the owner
 * (or an admin, for moderators) sends an offer, and the member accepts or declines it.
 */
class GroupRoleController extends Controller
{
    public function offer(Request $request, Group $group): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(GroupRoleOffer::ROLES)],
        ]);
        $mine = $group->roleOf($me);
        // Only the owner names admins or hands the group over; admins may add moderators.
        abort_unless($mine === 'owner' || ($mine === 'admin' && $data['role'] === 'moderator'), 403);

        $user = User::where('blocked', false)->findOrFail($data['user_id']);
        abort_if($user->is($me) || ! $group->roleOf($user) || $me->isBlockedWith($user), 422);
        abort_if($group->roleOf($user) === $data['role'], 422);

        $group->roleOffers()->updateOrCreate(['user_id' => $user->id], ['from_id' => $me->id, 'role' => $data['role']]);
        ClubActivity::send($user, 'group_offer_'.$data['role'], $me, route('groups.show', $group), 'group:'.$group->id, $group->name);

        return back();
    }

    public function accept(Request $request, Group $group, GroupRoleOffer $offer): RedirectResponse
    {
        $me = $request->user();
        abort_unless($offer->group_id === $group->id && $offer->user_id === $me->id, 404);
        // The one who offered may have lost the right to in the meantime.
        $theirs = $group->roleOf($offer->from);
        abort_unless($theirs === 'owner' || ($theirs === 'admin' && $offer->role === 'moderator'), 409);
        abort_unless($group->roleOf($me), 409);

        DB::transaction(function () use ($group, $offer, $me) {
            if ($offer->role === 'owner') {
                // The former owner stays on as an admin.
                $group->members()->syncWithoutDetaching([$group->owner_id => ['role' => 'admin', 'approved' => true]]);
                $group->members()->updateExistingPivot($me->id, ['role' => 'owner']);
                $group->update(['owner_id' => $me->id]);
                // Offers the former owner made no longer stand.
                $group->roleOffers()->whereKeyNot($offer->id)->delete();
            } else {
                $group->members()->updateExistingPivot($me->id, ['role' => $offer->role]);
            }
            $offer->delete();
        });
        Group::forgetRoles();
        ClubActivity::retract($me, 'group_offer_'.$offer->role, $offer->from, 'group:'.$group->id);
        ClubActivity::send($offer->from, 'group_offer_accepted', $me, route('groups.show', $group), 'group:'.$group->id, $group->name);

        return back();
    }

    /** Declined by the member, or taken back by whoever sent it or by the owner. */
    public function cancel(Request $request, Group $group, GroupRoleOffer $offer): RedirectResponse
    {
        $me = $request->user();
        abort_unless($offer->group_id === $group->id, 404);
        abort_unless(in_array($me->id, [$offer->user_id, $offer->from_id, $group->owner_id], true), 403);
        ClubActivity::retract($offer->user, 'group_offer_'.$offer->role, $offer->from, 'group:'.$group->id);
        $offer->delete();

        return back();
    }

    /** Back to a plain member: the owner for anyone, an admin for moderators, anyone for themselves. */
    public function demote(Request $request, Group $group, User $user): RedirectResponse
    {
        $me = $request->user();
        $theirs = $group->roleOf($user);
        abort_unless(in_array($theirs, ['admin', 'moderator'], true), 422);
        $mine = $group->roleOf($me);
        abort_unless($user->is($me) || $mine === 'owner' || ($mine === 'admin' && $theirs === 'moderator'), 403);
        $group->members()->updateExistingPivot($user->id, ['role' => 'member']);
        Group::forgetRoles();

        return back();
    }
}
