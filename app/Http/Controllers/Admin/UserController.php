<?php

namespace App\Http\Controllers\Admin;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('filter');

        $users = User::query()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('name', $this->like(), $this->pattern($q))
                ->orWhere('username', $this->like(), $this->pattern($q))
                ->orWhere('email', $this->like(), $this->pattern($q))))
            ->when($filter === 'blocked', fn (Builder $b) => $b->where('blocked', true))
            ->when($filter === 'admins', fn (Builder $b) => $b->where('is_admin', true))
            ->when($filter === 'unverified', fn (Builder $b) => $b->whereNull('email_verified_at'))
            ->withCount(['posts'])
            ->latest('id')
            ->paginate(30)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'avatar' => $u->avatar,
                'blocked' => $u->blocked,
                'verified' => $u->email_verified_at !== null,
                'is_admin' => $u->is_admin,
                'is_super' => $u->isSuperAdmin(),
                'posts_count' => $u->posts_count,
                'created_at' => $u->created_at?->toIso8601String(),
                'last_seen_at' => $u->last_seen_at?->toIso8601String(),
                'rules_accepted_at' => $u->rules_accepted_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
            'q' => $q,
            'filter' => $filter,
            'unverified' => User::whereNull('email_verified_at')->count(),
        ]);
    }

    /** Confirms the email of members whose letter never reached them, so they can come in. */
    public function verify(Request $request): RedirectResponse
    {
        User::whereIn('id', $this->ids($request))->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        return back();
    }

    /** Deletes sign-ups that never confirmed their email (mostly spam bots); confirmed members and admins are kept. */
    public function destroy(Request $request): RedirectResponse
    {
        User::whereIn('id', $this->ids($request))->whereNull('email_verified_at')->where('is_admin', false)
            ->get()->reject->isSuperAdmin()->each->delete();

        return back();
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        return $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer']])['ids'];
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'blocked' => ['sometimes', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);
        abort_if($user->is($request->user()), 422, 'You cannot change your own access.');
        abort_if($user->isSuperAdmin(), 422, 'The main administrator keeps their access.');

        $user->forceFill($data)->save();

        return back();
    }

    /** Blocks a spammer and removes everything they posted. */
    public function purge(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()) || $user->is_admin, 422, 'Administrators cannot be purged.');

        $user->forceFill(['blocked' => true])->save();
        Comment::where('user_id', $user->id)->delete();
        $user->posts()->each(fn ($post) => $post->delete());

        return back();
    }
}
