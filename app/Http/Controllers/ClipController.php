<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Models\Like;
use App\Models\Post;
use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/** Clips: vertical videos, watched one after another by swiping up. Members only. */
class ClipController extends Controller
{
    /** Clips sent at a time; the page asks for more as the end comes near. */
    private const BATCH = 8;

    /** The clips screen, from the newest, or from the clip opened in the feed. */
    public function index(Request $request, ?Post $post = null): Response
    {
        $first = $post ? self::query($request)->whereKey($post->id)->first() : null;
        $rest = self::query($request)->when($first, fn (Builder $q) => $q->whereKeyNot($first->id))->limit(self::BATCH)->get();
        $clips = $first ? $rest->prepend($first) : $rest;

        return Inertia::render('clips/index', [
            'clips' => $clips->map(fn (Post $p) => self::card($p, $request))->values()->all(),
            'next' => $rest->count() === self::BATCH ? $rest->last()->id : null,
            'skip' => $first?->id,
        ]);
    }

    /** The next clips after $before (a post id), for endless scrolling. */
    public function more(Request $request): JsonResponse
    {
        $data = $request->validate(['before' => ['required', 'integer'], 'skip' => ['nullable', 'integer']]);
        $clips = self::query($request)->where('posts.id', '<', $data['before'])
            ->when($data['skip'] ?? null, fn (Builder $q, $skip) => $q->whereKeyNot($skip))
            ->limit(self::BATCH)->get();

        return response()->json([
            'clips' => $clips->map(fn (Post $p) => self::card($p, $request))->values()->all(),
            'next' => $clips->count() === self::BATCH ? $clips->last()->id : null,
        ]);
    }

    /** Counts a view, once a day per member and clip. */
    public function view(Request $request, Post $post): HttpResponse
    {
        abort_unless($post->video_id && $post->isVisibleTo($request->user()), 404);
        if (Cache::add('clip-view:'.$post->id.':'.$request->user()->id, 1, now()->addDay())) {
            $post->video()->increment('views_count');
        }

        return response()->noContent();
    }

    /** Posts with a clip the member may see: by audience, outside private and hidden groups, without blocked people. */
    private static function query(Request $request): Builder
    {
        $viewer = $request->user();
        $hidden = $viewer->hiddenUserIds();
        $blockedGroups = $viewer->blockedGroupIds();

        return Post::query()->select('posts.*')
            ->whereHas('video', fn (Builder $v) => $v->where('is_clip', true))
            ->with(['user', 'video', 'contentTranslations' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->visibleTo($viewer)
            ->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereHas('group', fn (Builder $g) => $g->where('published', true)->where('is_private', false)))
            ->when($hidden->isNotEmpty(), fn (Builder $q) => $q->whereNotIn('posts.user_id', $hidden))
            ->when($blockedGroups->isNotEmpty(), fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereNotIn('group_id', $blockedGroups)))
            ->withCount(['likes', 'comments'])
            ->addSelect(['my_reaction' => Like::select('type')
                ->whereColumn('likeable_id', 'posts.id')->where('likeable_type', (new Post)->getMorphClass())
                ->where('user_id', $viewer->id)->limit(1)])
            ->orderByDesc('posts.id');
    }

    private static function card(Post $post, Request $request): array
    {
        $official = $post->isOfficial() && $post->user_id !== $request->user()->id;

        return [
            'id' => $post->id,
            'url' => route('posts.show', $post),
            'body' => Html::clean($post->body),
            'translation' => TranslationPayload::for($post, app()->getLocale(), app(ContentTranslator::class)),
            'author' => $official || ! $post->user ? null : PeopleController::card($post->user),
            'video' => $post->video->card(),
            'views' => $post->video->views_count,
            'likes_count' => $post->likes_count,
            'my_reaction' => $post->my_reaction,
            'comments_count' => $post->comments_count,
            'can_delete' => $request->user()->id === $post->user_id || $request->user()->is_admin,
        ];
    }
}
