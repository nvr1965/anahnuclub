<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ClubActivity;

/**
 * Badges shown beside a member's name.
 *
 * A level is earned: it is counted from the member's posts, comments, the reactions their posts get
 * and the days since they joined, and goes up (or down) by itself. A special badge is given by an
 * administrator, such as "Club author" for the accounts that post the club's news.
 * Beside the name goes the first special badge the member holds, otherwise their level from
 * "Club member" up; a plain member shows nothing.
 */
class Badges
{
    /** Levels, lowest first; the index is what users.level holds. */
    public const LEVELS = ['member', 'club-member', 'co-author', 'author', 'top-author'];

    /** Given by an administrator, in the order they win the place beside the name. */
    public const SPECIAL = ['club-author', 'founder', 'moderator', 'inner-circle', 'old-timer', 'ambassador', 'translator', 'beta-tester', 'verified', 'partner'];

    /** What a level asks for; every number must be reached. An administrator can change them. */
    public const DEFAULT_THRESHOLDS = [
        'club-member' => ['days' => 3, 'posts' => 1, 'comments' => 3, 'reactions' => 0],
        'co-author' => ['days' => 7, 'posts' => 5, 'comments' => 5, 'reactions' => 20],
        'author' => ['days' => 14, 'posts' => 25, 'comments' => 10, 'reactions' => 100],
        'top-author' => ['days' => 30, 'posts' => 100, 'comments' => 20, 'reactions' => 500],
    ];

    public const MEASURES = ['days', 'posts', 'comments', 'reactions'];

    /** @return array<string, array{days: int, posts: int, comments: int, reactions: int}> */
    public static function thresholds(): array
    {
        $saved = json_decode(Setting::get('badge_levels') ?? '[]', true) ?: [];
        $out = [];
        foreach (self::DEFAULT_THRESHOLDS as $level => $default) {
            foreach (self::MEASURES as $m) {
                $out[$level][$m] = max(0, (int) ($saved[$level][$m] ?? $default[$m]));
            }
        }

        return $out;
    }

    /** @param  array<string, array<string, int>>  $thresholds */
    public static function saveThresholds(array $thresholds): void
    {
        $clean = [];
        foreach (self::DEFAULT_THRESHOLDS as $level => $default) {
            foreach (self::MEASURES as $m) {
                $clean[$level][$m] = max(0, (int) ($thresholds[$level][$m] ?? $default[$m]));
            }
        }
        Setting::put('badge_levels', json_encode($clean));
    }

    /**
     * What counts towards a level, for each of these members: their own posts (not shares),
     * their comments, and the reactions other members left on their posts.
     *
     * @param  list<int>  $ids
     * @return array<int, array{posts: int, comments: int, reactions: int}>
     */
    public static function stats(array $ids): array
    {
        $posts = Post::query()->whereIn('user_id', $ids)->whereNull('shared_post_id')
            ->groupBy('user_id')->selectRaw('user_id, count(*) as n')->pluck('n', 'user_id');
        $comments = Comment::query()->whereIn('user_id', $ids)
            ->groupBy('user_id')->selectRaw('user_id, count(*) as n')->pluck('n', 'user_id');
        $reactions = Like::query()->join('posts', 'posts.id', '=', 'likes.likeable_id')
            ->where('likes.likeable_type', 'post')->whereIn('posts.user_id', $ids)
            ->whereColumn('likes.user_id', '!=', 'posts.user_id')
            ->groupBy('posts.user_id')->selectRaw('posts.user_id as owner, count(*) as n')->pluck('n', 'owner');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['posts' => (int) ($posts[$id] ?? 0), 'comments' => (int) ($comments[$id] ?? 0), 'reactions' => (int) ($reactions[$id] ?? 0)];
        }

        return $out;
    }

    /**
     * The level these numbers reach. Only members who confirmed their email, accepted the rules
     * and are not blocked rise above a plain member.
     *
     * @param  array{posts: int, comments: int, reactions: int}  $stats
     */
    public static function levelFor(User $user, array $stats, ?array $thresholds = null): int
    {
        if ($user->blocked || ! $user->email_verified_at || ! $user->rules_accepted_at) {
            return 0;
        }
        $have = $stats + ['days' => (int) floor(($user->created_at ?? now())->diffInDays(now(), true))];
        $level = 0;
        foreach (array_values($thresholds ?? self::thresholds()) as $i => $needs) {
            foreach (self::MEASURES as $m) {
                if ($have[$m] < $needs[$m]) {
                    return $level;
                }
            }
            $level = $i + 1;
        }

        return $level;
    }

    /** Counts a member's level again, after they posted, commented or got a reaction. */
    public static function refresh(User|int|null $user, bool $notify = true): void
    {
        $user = is_int($user) ? User::find($user) : $user;
        if (! $user) {
            return;
        }
        self::apply($user, self::levelFor($user, self::stats([$user->id])[$user->id]), $notify);
    }

    /** Every member's level, e.g. each night, when days since joining lift some of them. */
    public static function refreshAll(bool $notify = true): void
    {
        $thresholds = self::thresholds();
        User::query()->whereNotNull('email_verified_at')->orWhere('level', '>', 0)
            ->chunkById(500, function ($users) use ($thresholds, $notify) {
                $stats = self::stats($users->modelKeys());
                foreach ($users as $user) {
                    self::apply($user, self::levelFor($user, $stats[$user->id], $thresholds), $notify);
                }
            });
    }

    private static function apply(User $user, int $level, bool $notify): void
    {
        $before = (int) $user->level;
        if ($before === $level) {
            return;
        }
        // Not through save(): the level is no edit of the profile and must not touch its time.
        User::whereKey($user->id)->update(['level' => $level]);
        $user->level = $level;
        $user->syncOriginalAttribute('level');
        if ($notify && $level > $before) {
            ClubActivity::award($user, 'level_up', self::LEVELS[$level], $user);
        }
    }

    /** @return list<string> the special badges a member holds, in their order of rank */
    public static function special(User $user): array
    {
        return array_values(array_intersect(self::SPECIAL, $user->badges ?? []));
    }

    /** Gives or takes a special badge; the member hears of a new one under the bell. */
    public static function set(User $user, string $badge, bool $on, ?User $by = null): void
    {
        $held = self::special($user);
        $now = $on ? array_values(array_intersect(self::SPECIAL, [...$held, $badge])) : array_values(array_diff($held, [$badge]));
        if ($now === $held) {
            return;
        }
        $user->forceFill(['badges' => $now ?: null])->saveQuietly();
        if ($on && $by) {
            ClubActivity::award($user, 'badge_given', $badge, $by);
        }
    }

    /** The badge beside the name: the first special one, otherwise the level from "Club member" up. */
    public static function primary(User $user): ?string
    {
        return self::special($user)[0] ?? ((int) $user->level > 0 ? self::LEVELS[(int) $user->level] ?? null : null);
    }

    /** Keeps levels up to date as members post, comment and react. */
    public static function listen(): void
    {
        $post = fn (Post $p) => $p->user_id && self::refresh($p->user_id);
        Post::created($post);
        Post::deleted($post);
        $comment = fn (Comment $c) => $c->user_id && self::refresh($c->user_id);
        Comment::created($comment);
        Comment::deleted($comment);
        $like = function (Like $l) {
            if ($l->likeable_type === 'post' && ($owner = Post::whereKey($l->likeable_id)->value('user_id')) && $owner !== $l->user_id) {
                self::refresh((int) $owner);
            }
        };
        Like::created($like);
        Like::deleted($like);
    }
}
