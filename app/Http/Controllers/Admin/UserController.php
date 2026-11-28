<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Http\Controllers\Admin;

use App\Models\Comment;
use App\Models\User;
use App\Support\Badges;
use App\Support\BannedEmails;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('filter');
        $badge = $request->query('badge');
        $f = $this->filters($request);

        $users = User::query()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('name', $this->like(), $this->pattern($q))
                ->orWhere('username', $this->like(), $this->pattern($q))
                ->orWhere('email', $this->like(), $this->pattern($q))))
            ->when($filter === 'blocked', fn (Builder $b) => $b->where('blocked', true))
            ->when($filter === 'admins', fn (Builder $b) => $b->where('is_admin', true))
            ->when($filter === 'unverified', fn (Builder $b) => $b->whereNull('email_verified_at'))
            ->when($filter === 'banned', fn (Builder $b) => $b->whereRaw('1 = 0'))
            ->when(in_array($badge, Badges::SPECIAL, true), fn (Builder $b) => $b->whereJsonContains('badges', $badge))
            ->when(in_array($badge, Badges::LEVELS, true), fn (Builder $b) => $b->where('level', array_search($badge, Badges::LEVELS, true)))
            ->when($f['posts'] === 'with', fn (Builder $b) => $b->has('posts'))
            ->when($f['posts'] === 'without', fn (Builder $b) => $b->doesntHave('posts'))
            ->when($f['joined'], fn (Builder $b, int $days) => $b->where('created_at', '>=', now()->subDays($days)))
            ->when(in_array($f['seen'], [1, 7, 30], true), fn (Builder $b) => $b->where('last_seen_at', '>=', now()->subDays($f['seen'])))
            ->when($f['seen'] === 'idle', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(30))))
            ->when($f['rules'] === 'yes', fn (Builder $b) => $b->whereNotNull('rules_accepted_at'))
            ->when($f['rules'] === 'no', fn (Builder $b) => $b->whereNull('rules_accepted_at'))
            ->when($f['lang'], fn (Builder $b, string $lang) => $b->where(fn (Builder $w) => $w
                ->where('locale', $lang)->orWhere(fn (Builder $o) => $o->whereNull('locale')->where('ui_locale', $lang))))
            ->when($f['country'], fn (Builder $b, string $country) => $b->where('country', $country))
            ->withCount(['posts'])
            ->tap(fn (Builder $b) => match ($f['sort']) {
                'old' => $b->oldest('id'),
                'seen' => $b->orderByRaw('last_seen_at is null')->orderByDesc('last_seen_at')->orderByDesc('id'),
                'posts' => $b->orderByDesc('posts_count')->orderByDesc('id'),
                'name' => $b->orderByRaw('lower(name)')->orderBy('id'),
                default => $b->latest('id'),
            })
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
                'level' => Badges::LEVELS[$u->level] ?? 'member',
                'badges' => Badges::special($u),
                'created_at' => $u->created_at?->toIso8601String(),
                'last_seen_at' => $u->last_seen_at?->toIso8601String(),
                'rules_accepted_at' => $u->rules_accepted_at?->toIso8601String(),
                'country' => $u->country,
                'locale' => $u->preferredLocale(),
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
            'q' => $q,
            'filter' => $filter,
            'badge' => $badge,
            'filters' => $f,
            'countries' => User::query()->whereNotNull('country')->where('country', '!=', '')
                ->distinct()->orderBy('country')->pluck('country'),
            'special_badges' => Badges::SPECIAL,
            'unverified' => User::whereNull('email_verified_at')->count(),
            'banned' => $filter === 'banned'
                ? DB::table('banned_emails')->when($q !== '', fn ($b) => $b->where('email', $this->like(), $this->pattern($q)))->latest('id')->limit(500)->get(['id', 'email', 'name', 'created_at'])
                : null,
            'banned_count' => DB::table('banned_emails')->count(),
        ]);
    }

    /** Confirms the email of members whose letter never reached them, so they can come in. */
    public function verify(Request $request): RedirectResponse
    {
        User::whereIn('id', $this->ids($request))->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        return back();
    }

    /**
     * Deletes sign-ups that never confirmed their email and blocked members; other members and admins are kept.
     * The address of a blocked one goes to the black list, so they cannot sign up again.
     */
    public function destroy(Request $request): RedirectResponse
    {
        User::whereIn('id', $this->ids($request))->where('is_admin', false)
            ->where(fn (Builder $b) => $b->whereNull('email_verified_at')->orWhere('blocked', true))
            ->get()->reject->isSuperAdmin()
            ->each(function (User $user) {
                if ($user->blocked) {
                    BannedEmails::add($user);
                }
                $user->delete();
            });

        return back();
    }

    /** Takes an address off the black list. */
    public function unban(int $id): RedirectResponse
    {
        DB::table('banned_emails')->where('id', $id)->delete();

        return back();
    }

    /**
     * Sorting and the narrowing filters of the users list; anything unknown is dropped.
     *
     * @return array{sort: string, posts: ?string, joined: ?int, seen: int|string|null, rules: ?string, lang: ?string, country: ?string}
     */
    private function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array($v = (string) $request->query($key), $allowed, true) ? $v : null;
        $seen = $pick('seen', ['1', '7', '30', 'idle']);
        $code = fn (string $key) => preg_match('/^[A-Za-z-]{2,10}$/', $v = (string) $request->query($key)) ? $v : null;

        return [
            'sort' => $pick('sort', ['old', 'seen', 'posts', 'name']) ?? 'new',
            'posts' => $pick('posts', ['with', 'without']),
            'joined' => ($j = $pick('joined', ['1', '7', '30', '365'])) ? (int) $j : null,
            'seen' => $seen === null || $seen === 'idle' ? $seen : (int) $seen,
            'rules' => $pick('rules', ['yes', 'no']),
            'lang' => $code('lang'),
            'country' => ($c = $code('country')) ? strtoupper($c) : null,
        ];
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

    /** Gives or takes one of the special badges, such as "Club author" or "Beta tester". */
    public function badge(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'badge' => ['required', Rule::in(Badges::SPECIAL)],
            'on' => ['required', 'boolean'],
        ]);
        Badges::set($user, $data['badge'], $data['on'], $request->user());

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
