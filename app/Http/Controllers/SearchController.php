<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.addcslashes($q, '%_\\').'%';
        $op = $this->likeOperator();

        $results = ['people' => [], 'groups' => [], 'posts' => []];

        if (mb_strlen($q) >= 2) {
            $results['people'] = User::query()
                ->where('blocked', false)
                ->where(fn (Builder $w) => $w->where('name', $op, $like)->orWhere('username', $op, $like))
                ->limit(12)->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar]);

            $results['groups'] = Group::query()
                ->where('published', true)->where('is_private', false)
                ->where('name', $op, $like)
                ->withCount('members')
                ->limit(8)->get()
                ->map(fn (Group $g) => ['name' => $g->name, 'slug' => $g->slug, 'members_count' => $g->members_count]);

            $results['posts'] = PostResource::collection(
                FeedController::postQuery($request)
                    ->whereDoesntHave('group', fn (Builder $g) => $g->where('is_private', true))
                    ->where(fn (Builder $w) => $w->where('title', $op, $like)->orWhere('body', $op, $like))
                    ->limit(20)->get()
            );
        }

        return Inertia::render('search', [
            'q' => $q,
            'results' => $results,
            'sidebar' => CommunitySidebar::for($request->user()),
        ]);
    }

    /** Case-insensitive match: ILIKE on PostgreSQL, LIKE elsewhere (SQLite in tests is already case-insensitive for ASCII). */
    private function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
