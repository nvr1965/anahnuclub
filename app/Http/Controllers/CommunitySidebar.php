<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Photo;
use App\Models\User;

/** The side column shared by the welcome page, the feed and groups: members, groups, photos. */
class CommunitySidebar
{
    public static function for(?User $user): array
    {
        return [
            'members' => User::query()
                ->where('blocked', false)
                ->whereNotNull('avatar_path')
                ->when($user, fn ($q) => $q->whereNotIn('id', $user->hiddenUserIds()))
                ->orderByDesc('last_seen_at')
                ->limit(20)
                ->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar]),
            'members_count' => User::where('blocked', false)->count(),
            'groups' => ($user ? $user->groups() : Group::query())
                ->where('published', true)
                ->where('is_private', false)
                ->when($user, fn ($q) => $q->whereNotIn('groups.id', $user->blockedGroupIds()))
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->limit(6)
                ->get()
                ->map(fn (Group $g) => ['name' => $g->name, 'slug' => $g->slug, 'posts_count' => $g->posts_count]),
            'photos' => Photo::query()
                ->whereNotNull('thumb_path')
                ->whereHas('album', fn ($q) => $q->whereNull('group_id')
                    ->when($user, fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $user->hiddenUserIds()))))
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Photo $p) => ['id' => $p->id, 'thumb' => asset('storage/'.$p->thumb_path), 'url' => asset('storage/'.$p->path)]),
        ];
    }
}
