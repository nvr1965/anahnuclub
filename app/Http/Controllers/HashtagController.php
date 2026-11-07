<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Support\PostTags;
use App\Support\SiteMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** #Hashtags: the page of every post carrying one, and hints while typing one in the composer. */
class HashtagController extends Controller
{
    /** How far back "popular" looks. */
    private const POPULAR_DAYS = 30;

    public function show(Request $request, string $tag): Response|RedirectResponse
    {
        $tag = rawurldecode($tag);
        abort_unless(PostTags::isHashtag($tag), 404);
        // One address per hashtag: #Шаббат and #шаббат open /hashtag/шаббат.
        $normal = PostTags::normalize($tag);
        if ($normal !== $tag) {
            return redirect(PostTags::url($normal), 301);
        }

        $viewer = $request->user();
        $myGroups = $viewer?->groups()->pluck('groups.id') ?? collect();
        $posts = FeedController::postQuery($request)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('post_hashtags')->whereColumn('post_hashtags.post_id', 'posts.id')->where('post_hashtags.tag', $normal))
            // Posts of a private group only for its members.
            ->whereDoesntHave('group', fn (Builder $g) => $g->where('is_private', true)->whereNotIn('id', $myGroups))
            ->simplePaginate(20)
            ->withQueryString();

        $heading = '#'.$normal;

        return Inertia::render('hashtags/show', [
            'tag' => $normal,
            'posts' => PostResource::collection($posts),
            'popular' => self::popular(),
            'sidebar' => CommunitySidebar::for($viewer),
        ])->withViewData(['meta' => SiteMeta::page($heading, __('Posts with :tag', ['tag' => $heading]), [
            'content' => ['heading' => $heading, 'html' => '', 'links' => []],
        ])]);
    }

    /** Hashtags starting with what is typed after # in the composer, the most used first. */
    public function suggest(Request $request): JsonResponse
    {
        $q = PostTags::normalize((string) $request->query('q', ''));
        if ($q === '' || ! PostTags::isHashtag($q)) {
            return response()->json([]);
        }

        return response()->json(DB::table('post_hashtags')
            ->where('tag', 'like', addcslashes($q, '%_\\').'%')
            ->groupBy('tag')
            ->orderByRaw('count(*) desc')
            ->limit(8)
            ->pluck('tag'));
    }

    /**
     * The hashtags most used lately in posts anyone may read.
     *
     * @return list<string>
     */
    public static function popular(int $limit = 12): array
    {
        return DB::table('post_hashtags')
            ->join('posts', 'posts.id', '=', 'post_hashtags.post_id')
            ->leftJoin('groups', 'groups.id', '=', 'posts.group_id')
            ->where('post_hashtags.created_at', '>=', now()->subDays(self::POPULAR_DAYS))
            ->where('posts.audience', 'public')
            ->where(fn ($q) => $q->whereNull('posts.group_id')->orWhere(fn ($q) => $q->where('groups.is_private', false)->where('groups.published', true)))
            ->groupBy('post_hashtags.tag')
            ->orderByRaw('count(*) desc')
            ->orderBy('post_hashtags.tag')
            ->limit($limit)
            ->pluck('post_hashtags.tag')
            ->all();
    }
}
