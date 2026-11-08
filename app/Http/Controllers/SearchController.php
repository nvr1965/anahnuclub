<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Event;
use App\Models\Group;
use App\Models\Page;
use App\Models\User;
use App\Models\Video;
use App\Support\PostTags;
use App\Support\TextSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** One search box for people, posts (with their comments and translations), groups, videos, events and site pages. */
class SearchController extends Controller
{
    public const TYPES = ['people', 'posts', 'groups', 'videos', 'events', 'pages'];

    /** How many of each kind the "All" tab shows. */
    private const PREVIEW = ['people' => 6, 'posts' => 5, 'groups' => 4, 'videos' => 4, 'events' => 4, 'pages' => 4];

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $q = trim((string) $request->query('q'));
        // "#шаббат" opens the hashtag's page.
        if (str_starts_with($q, '#') && PostTags::isHashtag($q)) {
            return redirect(PostTags::url($q));
        }
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'all';
        $counts = array_fill_keys(self::TYPES, 0);
        $results = array_fill_keys(self::TYPES, []);
        $more = false;

        if (mb_strlen($q) >= 2) {
            $terms = TextSearch::terms($q);
            $queries = $this->queries($request, $terms);
            $pages = $this->pages($terms);

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

    /**
     * Every word of the query must turn up in one of the fields (any case, anywhere in the text), so "новости израиля"
     * finds "Новости Израиля" and "Израиль: главные новости" alike.
     *
     * @param  list<string>  $terms
     * @return array<string, array{Builder, callable(Collection): mixed}>
     */
    private function queries(Request $request, array $terms): array
    {
        $viewer = $request->user();
        $myGroups = $viewer?->groups()->pluck('groups.id') ?? collect();
        $match = function (array $columns, ?callable $more = null) use ($terms) {
            return function (Builder $w) use ($columns, $more, $terms) {
                foreach ($terms as $term) {
                    $w->where(function (Builder $any) use ($columns, $more, $term) {
                        foreach ($columns as $column) {
                            TextSearch::orColumn($any, $column, $term);
                        }
                        if ($more) {
                            $more($any, $term);
                        }
                    });
                }
            };
        };
        $translated = fn (Builder $any, string $term) => $any->orWhereHas('contentTranslations', fn (Builder $t) => TextSearch::orColumn($t, 'text', $term));

        $people = User::query()
            ->where('blocked', false)
            ->listedFor($viewer)
            ->when($viewer, fn (Builder $w) => $w->whereNotIn('id', $viewer->hiddenUserIds()))
            // People and groups are found by their names: a word somewhere in an "about" or a description
            // brought in pages of unrelated (often spam) accounts and groups.
            ->where($match(['name', 'username']))
            ->latestSeen()->orderBy('id');

        $posts = FeedController::postQuery($request)
            ->whereDoesntHave('group', fn (Builder $g) => $g->where('is_private', true)->whereNotIn('id', $myGroups))
            ->where($match(['title', 'body'], function (Builder $any, string $term) use ($translated) {
                $translated($any, $term);
                $any->orWhereHas('comments', fn (Builder $c) => TextSearch::orColumn($c, 'body', $term));
            }))
            // Posts with the words in their own text come before those found through a comment or a translation.
            ->reorder();
        TextSearch::orderByColumns($posts, ['title', 'body'], $terms)->latest()->orderByDesc('id');

        $groups = Group::query()
            ->where('published', true)
            ->where(fn (Builder $w) => $w->where('is_private', false)->orWhereIn('id', $myGroups))
            ->when($viewer, fn (Builder $w) => $w->whereNotIn('id', $viewer->blockedGroupIds()))
            ->where($match(['name'], fn (Builder $any, string $term) => $any->orWhereHas(
                'contentTranslations',
                fn (Builder $t) => $t->where('field', 'name')->where(fn (Builder $w) => TextSearch::orColumn($w, 'text', $term))
            )))
            ->withCount('members')
            ->orderByDesc('members_count');

        $videos = VideoController::visible($request)
            ->with('user')
            ->where($match(['title', 'description'], $translated))
            ->latest();

        $events = EventController::visible($request)
            ->withCount(['attendees as going_count' => fn ($a) => $a->where('event_user.status', 'going')])
            ->where($match(['title', 'description', 'location'], $translated))
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
    /** @param  list<string>  $terms */
    private function pages(array $terms): Collection
    {
        $locale = app()->getLocale();

        return Page::query()->where('published', true)->orderBy('sort')->get()
            ->filter(fn (Page $p) => collect($p->content ?? [])->contains(fn ($c) => TextSearch::matches(($c['title'] ?? '').' '.strip_tags($c['body'] ?? ''), $terms)))
            ->map(fn (Page $p) => ['slug' => $p->slug, 'title' => $p->in($locale)['title']]);
    }
}
