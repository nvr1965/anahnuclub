<?php

namespace App\Http\Controllers\Admin;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $userId = $request->integer('user') ?: null;

        $posts = Post::query()
            ->with(['user:id,name', 'group:id,name,slug'])
            ->withCount('comments')
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('title', $this->like(), $this->pattern($q))
                ->orWhere('body', $this->like(), $this->pattern($q))))
            ->when($userId, fn (Builder $b) => $b->where('user_id', $userId))
            ->latest('id')
            ->paginate(30)->withQueryString()
            ->through(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $p->body)))), 160),
                'type' => $p->type,
                'author' => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name] : null,
                'group' => $p->group ? ['name' => $p->group->name, 'slug' => $p->group->slug] : null,
                'comments_count' => $p->comments_count,
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/posts', ['posts' => $posts, 'q' => $q, 'user' => $userId]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer']])['ids'];
        Post::whereIn('id', $ids)->get()->each->delete();

        return back();
    }
}
