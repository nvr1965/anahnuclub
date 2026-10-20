<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Event;
use App\Models\Group;
use App\Models\Page;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** One search box for people, posts (with their comments and translations), groups, videos, events and site pages. */
class SearchController extends Controller
{
    public const TYPES = ['people', 'posts', 'groups', 'videos', 'events', 'pages'];

    /** How many of each kind the "All" tab shows. */
    private const PREVIEW = ['people' => 6, 'posts' => 5, 'groups' => 4, 'videos' => 4, 'events' => 4, 'pages' => 4];

    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'all';
        $counts = array_fill_keys(self::TYPES, 0);
        $results = array_fill_keys(self::TYPES, []);
        $more = false;

        if (mb_strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $queries = $this->queries($request, $like);
            $pages = $this->pages($q);

            foreach ($queries as $key => [$query, $map]) {
                $counts[$key] = (clone $query)->count();
                if ($type === 'all') {
                    $results[$key] = $map((clone $query)->limit(self::PREVIEW[$key])->get());
                }
            }
            $counts['pages'] = $pages->count();
            $results['pages'] = $type === 'all' ? $pages->take(self::PREVIEW['pages'])->values() : [];

            if ($type === 'pages') {
                $results['pages'] = $pages->values();
            } elseif ($type !== 'all') {
                [$query, $map] = $queries[$type];
                $page = (clone $query)->simplePaginate($type === 'people' ? 48 : 20)->withQueryString();
                $results[$type] = $map($page->getCollection());
                $more = $page->nextPageUrl();
            }
        }

        return Inertia::render('search', [
            'q' => $q,
            'type' => $type,
            'counts' => $counts,
            'results' => $results,
            'more' => $more ?: null,
            'sidebar' => CommunitySidebar::for($request->user()),
        ]);
    }

    /** @return array<string, array{Builder, callable(Collection): mixed}> */
    private function queries(Request $request, string $like): array
    {
        $op = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $viewer = $request->user();
        $myGroups = $viewer?->groups()->pluck('groups.id') ?? collect();
        $translated = fn (Builder $t) => $t->where('text', $op, $like);

        $people = User::query()
            ->where('blocked', false)
            ->when($viewer, fn (Builder $w) => $w->whereNotIn('id', $viewer->hiddenUserIds()))
            ->where(fn (Builder $w) => $w->where('name', $op, $like)->orWhere('username', $op, $like)
                // Location can be hidden from some viewers, so only the open "about" text is searched.
                ->orWhere('about', $op, $like))
            ->orderByRaw('last_seen_at is null')->orderByDesc('last_seen_at')->orderBy('id');

        $posts = FeedController::postQuery($request)
            ->whereDoesntHave('group', fn (Builder $g) => $g->where('is_private', true)->whereNotIn('id', $myGroups))
            ->where(fn (Builder $w) => $w->where('title', $op, $like)->orWhere('body', $op, $like)
                ->orWhereHas('contentTranslations', $translated)
                ->orWhereHas('comments', fn (Builder $c) => $c->where('body', $op, $like)));

        $groups = Group::query()
            ->where('published', true)
            ->where(fn (Builder $w) => $w->where('is_private', false)->orWhereIn('id', $myGroups))
            ->when($viewer, fn (Builder $w) => $w->whereNotIn('id', $viewer->blockedGroupIds()))
            ->where(fn (Builder $w) => $w->where('name', $op, $like)->orWhere('description', $op, $like)->orWhereHas('contentTranslations', $translated))
            ->withCount('members')
            ->orderByDesc('members_count');

        $videos = VideoController::visible($request)
            ->with('user')
            ->where(fn (Builder $w) => $w->where('title', $op, $like)->orWhere('description', $op, $like)->orWhereHas('contentTranslations', $translated))
            ->latest();

        $events = EventController::visible($request)
            ->withCount(['attendees as going_count' => fn ($a) => $a->where('event_user.status', 'going')])
            ->where(fn (Builder $w) => $w->where('title', $op, $like)->orWhere('description', $op, $like)->orWhere('location', $op, $like)->orWhereHas('contentTranslations', $translated))
            ->orderByDesc('starts_at');

        return [
            'people' => [$people, fn (Collection $c) => $c->map(fn (User $u) => PeopleController::card($u))],
            'posts' => [$posts, fn (Collection $c) => PostResource::collection($c)],
            'groups' => [$groups, fn (Collection $c) => $c->map(fn (Group $g) => ['name' => $g->name, 'slug' => $g->slug, 'members_count' => $g->members_count, 'is_private' => $g->is_private])],
            'videos' => [$videos, fn (Collection $c) => $c->map(fn (Video $v) => $v->card() + ['author' => $v->user ? PeopleController::card($v->user) : null])],
            'events' => [$events, fn (Collection $c) => $c->map(fn (Event $e) => EventController::card($e, $viewer))],
        ];
    }

    /** Site pages are few, and their text sits in JSON per language, so they are matched here. */
    private function pages(string $q): Collection
    {
        $locale = app()->getLocale();

        return Page::query()->where('published', true)->orderBy('sort')->get()
            ->filter(fn (Page $p) => collect($p->content ?? [])->contains(fn ($c) => mb_stripos(($c['title'] ?? '').' '.strip_tags($c['body'] ?? ''), $q) !== false))
            ->map(fn (Page $p) => ['slug' => $p->slug, 'title' => $p->in($locale)['title']]);
    }
}
