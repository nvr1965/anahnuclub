<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Page;
use App\Models\Post;
use App\Support\LegacyUrl;
use App\Support\SiteMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** What search engines read besides the pages: robots.txt, the sitemap, and the old site's addresses. */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = SiteMeta::indexable()
            ? ['User-agent: *', ...array_map(fn ($path) => "Disallow: /$path", ['admin', 'settings', 'messages', 'calls', 'notifications',
                'search', 'link-preview', 'translations', 'locale', 'login', 'forgot-password', 'reset-password', 'verify-email', 'auth/']),
                '', 'Sitemap: '.route('sitemap')]
            // A test copy of the site stays out of search results.
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** The list of sitemap files: the site's pages, then posts in parts small enough for Google. */
    public function index(): Response
    {
        $parts = max(1, (int) ceil(self::publicPosts()->count() / self::postsPerPart()));
        $files = [route('sitemap.pages'), ...array_map(fn ($n) => route('sitemap.posts', $n), range(1, $parts))];

        return $this->xml(view('seo.sitemap-index', ['files' => $files])->render());
    }

    public function pages(): Response
    {
        $urls = collect([route('home'), route('register'), route('contact')])
            ->map(fn ($url) => ['loc' => $url === route('home') ? $url.'/' : $url, 'lastmod' => null])
            ->concat(Page::where('published', true)->get()
                ->map(fn (Page $page) => ['loc' => route('pages.show', $page), 'lastmod' => $page->updated_at]));

        return $this->urlset($urls->all());
    }

    public function posts(int $part): Response
    {
        $size = self::postsPerPart();
        abort_if($part < 1, 404);
        $posts = Cache::remember("sitemap.posts.$part", now()->addHour(), fn () => self::publicPosts()
            ->orderBy('id')->skip(($part - 1) * $size)->take($size)->get(['id', 'created_at', 'updated_at'])
            ->map(fn (Post $post) => ['loc' => route('posts.show', $post), 'lastmod' => $post->updated_at ?? $post->created_at])->all());
        abort_if($posts === [] && $part > 1, 404);

        return $this->urlset($posts);
    }

    /** An address that is not a page here: an old site's one goes where that content lives now (301). */
    public function legacy(Request $request): RedirectResponse
    {
        if ($url = LegacyUrl::find($request)) {
            return redirect($url, 301);
        }
        abort_unless(LegacyUrl::isLegacy($request), 404);

        return redirect()->route('home', status: 301);
    }

    /** Posts anyone may open from a link: public, outside private or hidden groups, by members not blocked. */
    public static function publicPosts(): Builder
    {
        return Post::query()->visibleTo(null)
            ->where(fn (Builder $q) => $q->whereNull('group_id')
                ->orWhereHas('group', fn (Builder $g) => $g->where('published', true)->where('is_private', false)))
            ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereHas('user', fn (Builder $u) => $u->where('blocked', false)));
    }

    /** Each post is listed once per language, plus once without one. */
    private static function postsPerPart(): int
    {
        return max(1, intdiv(config('seo.sitemap_urls'), Language::enabled()->count() + 1));
    }

    /** @param  list<array{loc: string, lastmod: mixed}>  $urls */
    private function urlset(array $urls): Response
    {
        return $this->xml(view('seo.sitemap', ['urls' => $urls, 'languages' => Language::enabled()->pluck('code')])->render());
    }

    private function xml(string $body): Response
    {
        return response($body)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
