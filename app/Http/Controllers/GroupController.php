<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Group;
use App\Support\Html;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = Group::query()
            ->where('published', true)
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
            ],
            'posts' => PostResource::collection($posts),
            'sidebar' => CommunitySidebar::for($request->user()),
        ]);
    }

    public function join(Request $request, Group $group)
    {
        $group->members()->syncWithoutDetaching([$request->user()->id => ['approved' => ! $group->is_private]]);

        return back();
    }
}
