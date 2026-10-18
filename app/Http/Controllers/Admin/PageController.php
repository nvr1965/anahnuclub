<?php

namespace App\Http\Controllers\Admin;

use App\Models\Language;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Html;
use App\Translation\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Site pages in every language, and the site-wide links of the footer. */
class PageController extends AdminController
{
    public function index(Request $request): Response
    {
        $languages = Language::orderBy('sort')->get(['code', 'native_name', 'rtl', 'enabled']);
        $pages = Page::orderBy('sort')->orderBy('id')->get();
        $edit = $request->query('page') === 'new' ? new Page(['content' => [], 'in_footer' => true, 'published' => true]) : $pages->firstWhere('slug', $request->query('page'));

        return Inertia::render('admin/pages', [
            'languages' => $languages,
            'pages' => $pages->map(fn (Page $p) => [
                'slug' => $p->slug,
                'title' => $p->in(app()->getLocale())['title'],
                'published' => $p->published,
                'in_footer' => $p->in_footer,
                'languages' => collect($p->content)->filter(fn ($v) => trim($v['title'] ?? '') !== '')->keys()->values(),
            ]),
            'edit' => $edit ? [
                'slug' => $edit->exists ? $edit->slug : null,
                'content' => (object) $edit->content,
                'in_footer' => $edit->in_footer,
                'published' => $edit->published,
                'sort' => $edit->sort ?? 0,
            ] : null,
            'donate_links' => Setting::donateLinks(),
            'can_translate' => app(Translator::class)->name() !== 'null',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = Page::create($this->validated($request));

        return to_route('admin.pages.index', ['page' => $page->slug]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));

        return to_route('admin.pages.index', ['page' => $page->slug]);
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return to_route('admin.pages.index');
    }

    /** Fills the languages the page has no text in yet, translating from the one being edited. */
    public function translate(Request $request, Page $page, Translator $translator): RedirectResponse
    {
        $from = $request->validate(['from' => ['required', 'string']])['from'];
        $page->translateMissing($translator, Language::pluck('code')->all(), $from);

        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'donate_links' => ['present', 'array', 'max:10'],
            'donate_links.*.label' => ['required', 'string', 'max:40'],
            'donate_links.*.url' => ['required', 'url:https,http', 'max:500'],
        ]);
        $links = array_map(fn ($l) => ['label' => trim($l['label']), 'url' => trim($l['url'])], $data['donate_links']);
        Setting::put('donate_links', json_encode(array_values($links), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return back();
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'content' => ['required', 'array'],
            'content.*.title' => ['nullable', 'string', 'max:190'],
            'content.*.body' => ['nullable', 'string', 'max:100000'],
            'in_footer' => ['boolean'],
            'published' => ['boolean'],
            'sort' => ['nullable', 'integer'],
        ]);

        $codes = Language::pluck('code')->all();
        $content = [];
        foreach ($data['content'] as $code => $text) {
            if (in_array($code, $codes, true) && trim($text['title'] ?? '') !== '') {
                // Text an administrator saved by hand is no longer marked as a machine translation.
                $old = $page?->content[$code] ?? null;
                $same = $old && $old['title'] === $text['title'] && ($old['body'] ?? '') === Html::clean($text['body'] ?? '');
                $content[$code] = ['title' => trim($text['title']), 'body' => Html::clean($text['body'] ?? '')] + ($same && ! empty($old['auto']) ? ['auto' => true] : []);
            }
        }
        abort_if($content === [], 422, 'The page needs a title in at least one language.');

        $first = reset($content)['title'];

        return [
            'slug' => $data['slug'] ?: ($page?->slug ?? (Str::slug($first) ?: 'page-'.Str::lower(Str::random(6)))),
            'content' => $content,
            'in_footer' => $data['in_footer'] ?? true,
            'published' => $data['published'] ?? true,
            'sort' => $data['sort'] ?? 0,
        ];
    }
}
