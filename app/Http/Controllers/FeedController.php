<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Like;
use App\Models\Post;
use App\Support\LegacyUrl;
use App\Support\SiteMeta;
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
        // The old site without friendly URLs: index.php?option=com_community&view=…
        if (LegacyUrl::isLegacy($request) || LegacyUrl::isFeed($request)) {
            $url = LegacyUrl::isFeed($request) ? null : LegacyUrl::find($request);

            abort_unless($url, 410);

            return redirect($url, 301);
        }
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
        ])->withViewData(['meta' => array_merge(SiteMeta::default(), [
            'title' => 'anahnu.club - '.__('The best social network without censorship'),
            // The latest posts as plain links, so search engines find them without running the page.
            'content' => [
                'heading' => __('The best social network without censorship'),
                'html' => '<p>'.e(__('Write in your language, everything is translated for you.')).'</p>',
                'links' => $posts->map(fn (Post $post) => [
                    'href' => route('posts.show', $post),
                    'text' => $post->cachedTranslation('title', app()->getLocale()) ?? $post->title
                        ?: SiteMeta::plain($post->cachedTranslation('body', app()->getLocale()) ?? $post->body, 90),
                ])->all(),
            ],
        ])]);
    }

    /** Posts with everything a post card shows, newest first. */
    public static function postQuery(Request $request): Builder
    {
        $locale = app()->getLocale();
        $viewer = $request->user();
        $hidden = $viewer?->hiddenUserIds() ?? collect();

        return Post::query()
            ->select('posts.*')
            ->with([
                'user', 'group', 'photos', 'video', 'event', 'linkPreview',
                'comments' => fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $hidden)),
                'comments.user',
                'contentTranslations' => fn ($q) => $q->where('locale', $locale),
                'comments.contentTranslations' => fn ($q) => $q->where('locale', $locale),
            ])
            // Each post's audience: public, friends, some friends, or the author only.
            ->visibleTo($viewer)
            // Posts of groups an administrator hid stay out of every feed.
            ->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereHas('group', fn (Builder $g) => $g->where('published', true)))
            // People blocked either way, and groups the reader blocked, are never shown.
            ->when($hidden->isNotEmpty(), fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $hidden)))
            ->when($viewer && $viewer->blockedGroupIds()->isNotEmpty(), fn (Builder $q) => $q->where(
                fn (Builder $q) => $q->whereNull('group_id')->orWhereNotIn('group_id', $viewer->blockedGroupIds())
            ))
            ->withCount('likes')
            // How many of each reaction, for the faces beside the count.
            ->withCount(collect(Like::TYPES)->mapWithKeys(fn ($type) => ["likes as {$type}_reactions" => fn ($q) => $q->where('type', $type)])->all())
            ->addSelect(['my_reaction' => Like::select('type')
                ->whereColumn('likeable_id', 'posts.id')->where('likeable_type', (new Post)->getMorphClass())
                ->where('user_id', $viewer?->id)->limit(1)])
            ->latest();
    }
}
