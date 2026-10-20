<?php

namespace App\Http\Controllers\Admin;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Moderation\ContentScanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Posts and comments that may break the rules (drugs, porn, prostitution, gambling), found by words in any language. */
class ModerationController extends AdminController
{
    /** The newest ones are checked; older content was looked through when the site moved. */
    private const SCAN_LIMIT = 20000;

    public function index(Request $request): Response
    {
        $category = in_array($request->query('category'), ContentScanner::CATEGORIES, true) ? $request->query('category') : null;
        $scanner = new ContentScanner;
        $items = collect();

        // Not yet looked at, or changed since an admin kept it.
        $unchecked = fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('moderated_at')->orWhereColumn('updated_at', '>', 'moderated_at'));
        // On PostgreSQL the database narrows it down first; the exact check runs on what it finds.
        $candidates = function (Builder $q, array $columns) {
            if (DB::getDriverName() !== 'pgsql') {
                return;
            }
            $pattern = ContentScanner::databasePattern();
            $table = $q->getModel()->getTable();
            $type = $q->getModel()->getMorphClass();
            $q->where(function ($w) use ($columns, $pattern, $table, $type) {
                foreach ($columns as $column) {
                    $w->orWhereRaw("$table.$column ~* ?", [$pattern]);
                }
                $w->orWhereExists(fn ($e) => $e->from('content_translations')->whereColumn('translatable_id', "$table.id")
                    ->where('translatable_type', $type)->whereRaw('text ~* ?', [$pattern]));
            });
        };
        $translations = fn (string $type, array $ids) => DB::table('content_translations')->where('translatable_type', $type)
            ->whereIn('translatable_id', $ids)->get(['translatable_id', 'text'])->groupBy('translatable_id');

        Post::query()->tap($unchecked)->tap(fn ($q) => $candidates($q, ['title', 'body']))->latest('id')->with('user:id,name,blocked')
            ->lazy(500)->take(self::SCAN_LIMIT)->chunk(500)->each(function ($posts) use ($scanner, $translations, $items) {
                $more = $translations((new Post)->getMorphClass(), $posts->pluck('id')->all());
                foreach ($posts as $p) {
                    $hits = $scanner->scan([$p->title, $p->body, ...$more->get($p->id, collect())->pluck('text')->all()]);
                    if ($hits) {
                        $items->push($this->row('post', $p->id, $p->title, $p->body, $p->user, $p->created_at, $hits, route('posts.show', $p)));
                    }
                }
            });
        Comment::query()->tap($unchecked)->tap(fn ($q) => $candidates($q, ['body']))->latest('id')->with('user:id,name,blocked')
            ->lazy(500)->take(self::SCAN_LIMIT)->chunk(500)->each(function ($comments) use ($scanner, $translations, $items) {
                $more = $translations((new Comment)->getMorphClass(), $comments->pluck('id')->all());
                foreach ($comments as $c) {
                    $hits = $scanner->scan([$c->body, ...$more->get($c->id, collect())->pluck('text')->all()]);
                    if ($hits) {
                        $items->push($this->row('comment', $c->id, null, $c->body, $c->user, $c->created_at, $hits, route('posts.show', $c->post_id)));
                    }
                }
            });

        $counts = collect(ContentScanner::CATEGORIES)->mapWithKeys(fn ($c) => [$c => $items->filter(fn ($i) => isset($i['hits'][$c]))->count()]);
        $shown = $category ? $items->filter(fn ($i) => isset($i['hits'][$category])) : $items;
        $authors = $shown->whereNotNull('author')->groupBy('author.id')
            ->map(fn ($rows) => $rows->first()['author'] + ['count' => $rows->count()])
            ->sortByDesc('count')->values()->take(30);

        return Inertia::render('admin/moderation', [
            'items' => $shown->sortByDesc('created_at')->values()->take(300),
            'total' => $shown->count(),
            'counts' => $counts,
            'authors' => $authors,
            'category' => $category,
            'words' => collect(ContentScanner::CATEGORIES)->mapWithKeys(fn ($c) => [$c => (json_decode(Setting::get('moderation_words') ?? '{}', true) ?: [])[$c] ?? ''])->all(),
            'builtin' => collect(ContentScanner::WORDS)->map(fn ($words) => array_map(fn ($w) => str_replace(['^', '\b', '?', '.'], '', $w), $words))->all(),
        ]);
    }

    /** Deletes the chosen posts and comments; with "author", everything flagged of that author. */
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:300'],
            'items.*.type' => ['required', 'in:post,comment'],
            'items.*.id' => ['required', 'integer'],
        ]);
        $items = collect($data['items']);
        Post::whereIn('id', $items->where('type', 'post')->pluck('id'))->get()->each->delete();
        Comment::whereIn('id', $items->where('type', 'comment')->pluck('id'))->get()->each->delete();

        return back();
    }

    /** "Nothing wrong here": kept, and not shown again unless edited. */
    public function keep(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:300'],
            'items.*.type' => ['required', 'in:post,comment'],
            'items.*.id' => ['required', 'integer'],
        ]);
        $items = collect($data['items']);
        // Without touching updated_at, which tells whether it was edited afterwards.
        Post::whereIn('id', $items->where('type', 'post')->pluck('id'))->toBase()->update(['moderated_at' => now()]);
        Comment::whereIn('id', $items->where('type', 'comment')->pluck('id'))->toBase()->update(['moderated_at' => now()]);

        return back();
    }

    /** Words admins add to the built-in lists. */
    public function words(Request $request): RedirectResponse
    {
        $data = $request->validate(collect(ContentScanner::CATEGORIES)->mapWithKeys(fn ($c) => ["words.$c" => ['nullable', 'string', 'max:5000']])->all());
        Setting::put('moderation_words', json_encode(array_map(fn ($v) => (string) $v, $data['words'] ?? []), JSON_UNESCAPED_UNICODE));

        return back();
    }

    private function row(string $type, int $id, ?string $title, ?string $body, $user, $created, array $hits, string $url): array
    {
        $text = ContentScanner::plain((string) $body);
        $first = collect($hits)->flatten()->first();
        $at = $first ? mb_stripos($text, $first) : false;
        // Around the first word found, so the reason is visible at once.
        $start = $at === false ? 0 : max(0, $at - 80);

        return [
            'key' => "$type:$id",
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'excerpt' => ($start > 0 ? '…' : '').Str::limit(mb_substr($text, $start), 260),
            'author' => $user ? ['id' => $user->id, 'name' => $user->name, 'blocked' => (bool) $user->blocked] : null,
            'created_at' => $created?->toIso8601String(),
            'hits' => $hits,
            'url' => $url,
        ];
    }
}
