<?php

namespace App\Http\Controllers\Admin;

use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $stale = $request->boolean('stale');
        // By name (А–Я), or by when the group was made: newest or oldest first.
        $sort = in_array($request->query('sort'), ['name', 'newest', 'oldest'], true) ? $request->query('sort') : ($stale ? 'oldest' : 'name');

        $groups = Group::query()
            ->with('owner:id,name')
            ->withCount(['members', 'posts'])
            ->when($q !== '', fn (Builder $b) => $b->where('name', $this->like(), $this->pattern($q)))
            ->when($stale, fn (Builder $b) => $b->stale())
            ->when($sort === 'name', fn (Builder $b) => $b->orderBy('name'))
            ->when($sort !== 'name', fn (Builder $b) => $b->orderBy('created_at', $sort === 'newest' ? 'desc' : 'asc')->orderBy('id', $sort === 'newest' ? 'desc' : 'asc'))
            ->paginate(30)->withQueryString()
            ->through(fn (Group $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'owner' => $g->owner?->name,
                'published' => $g->published,
                'is_private' => $g->is_private,
                'members_count' => $g->members_count,
                'posts_count' => $g->posts_count,
                'created_at' => $g->created_at?->toDateString(),
            ]);

        return Inertia::render('admin/groups', [
            'groups' => $groups,
            'q' => $q,
            'stale' => $stale,
            'sort' => $sort,
            'stale_count' => Group::query()->stale()->count(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->forceFill($request->validate([
            'published' => ['sometimes', 'boolean'],
            'is_private' => ['sometimes', 'boolean'],
        ]))->save();

        return back();
    }

    /** Deletes the group together with its posts. */
    public function destroy(Group $group): RedirectResponse
    {
        $group->posts()->each(fn ($post) => $post->delete());
        $group->purge();

        return back();
    }

    /** Deletes every group older than a year that never got a post, album, video or event. */
    public function pruneStale(): RedirectResponse
    {
        Group::query()->stale()->each(fn (Group $group) => $group->purge());

        return to_route('admin.groups.index');
    }
}
