<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Group;
use App\Models\User;
use App\Support\Html;
use App\Support\SiteMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = Group::query()
            ->where('published', true)
            ->when($request->user(), fn ($q, $viewer) => $q->whereNotIn('id', $viewer->blockedGroupIds()))
            ->withCount(['members', 'posts'])
            ->orderByDesc('posts_count')
            ->paginate(30)
            ->through(fn (Group $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'avatar' => $g->avatar_path ? asset('storage/'.$g->avatar_path) : null,
                'members_count' => $g->members_count,
                'posts_count' => $g->posts_count,
            ]);

        return Inertia::render('groups/index', ['groups' => $groups, 'sidebar' => CommunitySidebar::for($request->user())]);
    }

    public function show(Request $request, Group $group): Response
    {
        $member = $request->user() && $group->members()->whereKey($request->user()->id)->exists();
        abort_if(! $group->published && ! $request->user()?->is_admin, 404);
        abort_if($group->is_private && ! $member && ! $request->user()?->is_admin, 403);

        $posts = FeedController::postQuery($request)->where('group_id', $group->id)->paginate(20);

        return Inertia::render('groups/show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => Html::clean($group->description),
                'cover' => $group->cover_path ? asset('storage/'.$group->cover_path) : null,
                'members_count' => $group->members()->count(),
                'is_member' => $member,
                'blocked' => (bool) $request->user()?->blockedGroupIds()->contains($group->id),
                'role' => $role = $group->roleOf($request->user()),
                // An offer waiting for this member's answer.
                'offer' => ($offer = $group->roleOffers()->with('from')->where('user_id', $request->user()?->id)->first()) ? [
                    'id' => $offer->id,
                    'role' => $offer->role,
                    'from' => PeopleController::card($offer->from),
                ] : null,
            ],
            // The team and the members to pick from, for those who run the group.
            'team' => in_array($role, ['owner', 'admin'], true) ? $this->team($group) : null,
            'posts' => PostResource::collection($posts),
            'sidebar' => CommunitySidebar::for($request->user()),
        ])->withViewData(['meta' => [
            'url' => route('groups.show', $group),
            'title' => $group->name.' · anahnu.club',
            'description' => mb_strimwidth(trim(strip_tags((string) $group->description)), 0, 200, '…') ?: __('The best social network without censorship'),
            'image' => $group->cover_path ? asset('storage/'.$group->cover_path) : SiteMeta::image(),
        ]]);
    }

    private function team(Group $group): array
    {
        $offers = $group->roleOffers()->get()->keyBy('user_id');

        return $group->members()
            ->wherePivot('approved', true)
            ->where('blocked', false)
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (User $u) => PeopleController::card($u) + [
                'role' => $group->owner_id === $u->id ? 'owner' : (in_array($u->pivot->role, ['admin', 'moderator'], true) ? $u->pivot->role : 'member'),
                'offer' => ($o = $offers->get($u->id)) ? ['id' => $o->id, 'role' => $o->role] : null,
            ])
            ->sortBy(fn ($m) => array_search($m['role'], ['owner', 'admin', 'moderator', 'member'], true))
            ->values()
            ->all();
    }

    public function join(Request $request, Group $group)
    {
        abort_if($request->user()->blockedGroupIds()->contains($group->id), 422);
        $group->members()->syncWithoutDetaching([$request->user()->id => ['approved' => ! $group->is_private]]);
        Group::forgetRoles();

        return back();
    }
}
