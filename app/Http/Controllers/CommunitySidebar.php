<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;

/** The side column shared by the welcome page, the feed and groups: members, groups, photos. */
class CommunitySidebar
{
    public static function for(?User $user): array
    {
        return [
            'members' => User::query()
                ->where('blocked', false)
                ->showable()
                ->listedFor($user)
                ->when($user, fn ($q) => $q->whereNotIn('id', $user->hiddenUserIds()))
                ->latestSeen()
                ->limit(20)
                ->get()
                ->map(fn (User $u) => PeopleController::card($u)),
            'members_count' => User::where('blocked', false)->listedFor($user)->count(),
            'groups' => ($user ? $user->groups() : Group::query())
                ->where('published', true)
                ->where('is_private', false)
                ->when($user, fn ($q) => $q->whereNotIn('groups.id', $user->blockedGroupIds()))
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->limit(6)
                ->get()
                ->map(fn (Group $g) => ['name' => $g->name, 'slug' => $g->slug, 'posts_count' => $g->posts_count]),
            'photos' => self::photos($user),
        ];
    }

    /**
     * The latest six pictures the reader may see. Asking PostgreSQL for "pictures with no post or
     * with a post the reader may see" in one go made it check every post of the club on each page;
     * taking the newest pictures a few dozen at a time and checking just their posts reads only those.
     *
     * @return list<array{id: int, thumb: string, url: string}>
     */
    private static function photos(?User $user): array
    {
        $shown = [];
        $before = null;
        for ($round = 0; $round < 5 && count($shown) < 6; $round++) {
            $batch = Photo::query()
                ->whereNotNull('thumb_path')
                ->whereHas('album', fn ($q) => $q->whereNull('group_id')
                    ->when($user, fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $user->hiddenUserIds()))))
                ->when($before, fn ($q) => $q->where(fn ($q) => $q->where('created_at', '<', $before->created_at)
                    ->orWhere(fn ($q) => $q->where('created_at', $before->created_at)->where('id', '<', $before->id))))
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(40)
                ->get();
            if ($batch->isEmpty()) {
                break;
            }
            $postIds = $batch->pluck('post_id')->filter()->unique();
            $visible = $postIds->isEmpty() ? collect() : Post::query()->whereIn('posts.id', $postIds)->visibleTo($user)->pluck('posts.id')->flip();
            foreach ($batch as $photo) {
                if (count($shown) < 6 && ($photo->post_id === null || $visible->has($photo->post_id))) {
                    $shown[] = ['id' => $photo->id, 'thumb' => asset('storage/'.$photo->thumb_path), 'url' => asset('storage/'.$photo->path)];
                }
            }
            $before = $batch->last();
            if ($before->created_at === null) {
                break;
            }
        }

        return $shown;
    }
}
