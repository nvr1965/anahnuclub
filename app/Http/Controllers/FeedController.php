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
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    /** Posts on a page of the guests' archive. */
    private const ARCHIVE_PAGE = 50;

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

        $posts = self::guestPosts($request)->limit(10)->get();

        return Inertia::render('welcome', [
            'posts' => PostResource::collection($posts),
            'sidebar' => CommunitySidebar::for(null),
            'canResetPassword' => true,
        ])->withViewData(['meta' => array_merge(SiteMeta::default(), [
            'title' => 'anahnu.club - '.__('The best social network without censorship'),
            // The latest posts as plain links, so search engines find them without running the page,
            // and the way on to the older ones.
            'content' => [
                'heading' => __('The best social network without censorship'),
                'html' => '<p>'.e(__('Write in your language, everything is translated for you.')).'</p>',
                'links' => [...self::links($posts), ['href' => route('posts.archive'), 'text' => __('Posts').' →']],
            ],
            'jsonld' => SiteMeta::site(),
        ])]);
    }

    /**
     * All public posts for guests and search engines, page by page (/posts, /posts/page/2…), so that
     * every older post is a link or two away from the home page, not only listed in the sitemap.
     */
    public function archive(Request $request, int $page = 1): Response|RedirectResponse
    {
        if ($request->user()) {
            return to_route('dashboard');
        }
        if ($page === 1 && $request->route('page') !== null) {
            return redirect(route('posts.archive'), 301);
        }
        $posts = self::guestPosts($request)->paginate(self::ARCHIVE_PAGE, page: $page);
        abort_if($page > 1 && $posts->isEmpty(), 404);

        // Pages are addresses of their own (/posts/page/2), not ?page=2, which canonical addresses leave out.
        $address = fn (int $n) => $n === 1 ? route('posts.archive') : route('posts.archive.page', $n);
        $links = [
            'prev' => $page > 1 ? $address($page - 1) : null,
            'next' => $posts->hasMorePages() ? $address($page + 1) : null,
        ];
        $heading = __('Posts').($page > 1 ? ' · '.$page : '');

        return Inertia::render('welcome', [
            'posts' => ['data' => PostResource::collection($posts->getCollection())->resolve($request), 'links' => $links],
            'sidebar' => CommunitySidebar::for(null),
            'canResetPassword' => true,
        ])->withViewData(['meta' => SiteMeta::page($heading, null, [
            'content' => ['heading' => $heading, 'html' => '', 'links' => [
                ...self::links($posts->getCollection()),
                ...array_filter([
                    $links['prev'] ? ['href' => $links['prev'], 'text' => '← '.__('Newer')] : null,
                    $links['next'] ? ['href' => $links['next'], 'text' => __('Older').' →'] : null,
                ]),
            ]],
        ])]);
    }

    /** Public posts outside private groups, newest first: what a guest may read. */
    private static function guestPosts(Request $request): Builder
    {
        return self::postQuery($request)->whereDoesntHave('group', fn (Builder $q) => $q->where('is_private', true));
    }

    /** @return list<array{href: string, text: string}> each post as a link named by its title or first words */
    private static function links(Collection $posts): array
    {
        return $posts->map(fn (Post $post) => [
            'href' => route('posts.show', $post),
            'text' => $post->cachedTranslation('title', app()->getLocale()) ?? $post->title
                ?: SiteMeta::plain($post->cachedTranslation('body', app()->getLocale()) ?? $post->body, 90),
        ])->values()->all();
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
