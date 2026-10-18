<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $posts = self::postQuery($request)
            ->whereDoesntHave('group', fn (Builder $q) => $q->where('is_private', true))
            ->paginate(20);

        return Inertia::render('feed', [
            'posts' => PostResource::collection($posts),
            'sidebar' => CommunitySidebar::for($request->user()),
        ]);
    }

    /** Guests see the club's front page: who is here and the latest public posts. */
    public function welcome(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return to_route('dashboard');
        }

        $posts = self::postQuery($request)
            ->whereDoesntHave('group', fn (Builder $q) => $q->where('is_private', true))
            ->limit(10)
            ->get();

        return Inertia::render('welcome', [
            'posts' => PostResource::collection($posts),
            'sidebar' => CommunitySidebar::for(null),
            'canResetPassword' => true,
        ]);
    }

    /** Posts with everything a post card shows, newest first. */
    public static function postQuery(Request $request): Builder
    {
        $locale = app()->getLocale();
        $viewer = $request->user();
        $hidden = $viewer?->hiddenUserIds() ?? collect();

        return Post::query()
            ->with([
                'user', 'group', 'photos', 'video', 'event',
                'comments' => fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $hidden)),
                'comments.user',
                'contentTranslations' => fn ($q) => $q->where('locale', $locale),
                'comments.contentTranslations' => fn ($q) => $q->where('locale', $locale),
            ])
            // Posts of groups an administrator hid stay out of every feed.
            ->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereHas('group', fn (Builder $g) => $g->where('published', true)))
            // People blocked either way, and groups the reader blocked, are never shown.
            ->when($hidden->isNotEmpty(), fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $hidden)))
            ->when($viewer && $viewer->blockedGroupIds()->isNotEmpty(), fn (Builder $q) => $q->where(
                fn (Builder $q) => $q->whereNull('group_id')->orWhereNotIn('group_id', $viewer->blockedGroupIds())
            ))
            ->withCount('likes')
            ->withExists(['likes as liked' => fn ($q) => $q->where('user_id', $viewer?->id)])
            ->latest();
    }
}
