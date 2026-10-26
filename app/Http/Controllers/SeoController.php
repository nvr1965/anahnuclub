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
        $urls = collect([route('home'), route('posts.archive'), route('register'), route('contact')])
            ->map(fn ($url) => ['loc' => $url === route('home') ? $url.'/' : $url, 'lastmod' => null])
            ->concat(Page::where('published', true)->get()
                ->map(fn (Page $page) => ['loc' => route('pages.show', $page), 'lastmod' => $page->updated_at]))
            // The site's own pages are written in every language.
            ->map(fn (array $url) => $url + ['alternates' => SiteMeta::alternates($url['loc'])]);

        return $this->urlset($urls->all());
    }

    public function posts(int $part): Response
    {
        $size = self::postsPerPart();
        abort_if($part < 1, 404);
        // A post is listed in the languages its text is in, not in all the site's: the others would be
        // the same text under another menu, and search engines leave such copies unindexed.
        $posts = Cache::remember("sitemap.posts.v2.$part", now()->addHour(), fn () => self::publicPosts()
            ->with(['contentTranslations' => fn ($q) => $q->whereIn('field', ['title', 'body'])
                ->select(['id', 'translatable_type', 'translatable_id', 'field', 'locale', 'source_hash'])])
            ->orderBy('id')->skip(($part - 1) * $size)->take($size)->get(['id', 'title', 'body', 'locale', 'created_at', 'updated_at'])
            ->map(fn (Post $post) => [
                'loc' => $loc = route('posts.show', $post),
                'lastmod' => $post->updated_at ?? $post->created_at,
                'alternates' => SiteMeta::alternates($loc, $post->languages($post->contentTranslations)),
            ])->all());
        abort_if($posts === [] && $part > 1, 404);

        return $this->urlset($posts);
    }

    /** The IndexNow key file (/{key}.txt): it holds the key, which proves the site sent its pages itself. */
    public function indexNowKey(string $key): Response
    {
        abort_unless(SiteMeta::indexable() && $key === SiteMeta::indexNowKey(), 404);

        return response($key)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * An address that is not a page here: an old site's one goes where that content lives now (301).
     * Old pages with nothing here, and old feeds, answer "gone" (410), so search engines drop them;
     * sending them all to the home page made Google report them as soft 404s.
     */
    public function legacy(Request $request): RedirectResponse
    {
        if (! LegacyUrl::isFeed($request) && ($url = LegacyUrl::find($request))) {
            return redirect($url, 301);
        }
        abort_unless(LegacyUrl::isLegacy($request) || LegacyUrl::isFeed($request), 404);
        abort(410);
    }

    /** Posts anyone may open from a link: public, outside private or hidden groups, by members not blocked. */
    public static function publicPosts(): Builder
    {
        return Post::query()->visibleTo(null)
            ->where(fn (Builder $q) => $q->whereNull('group_id')
                ->orWhereHas('group', fn (Builder $g) => $g->where('published', true)->where('is_private', false)))
            ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereHas('user', fn (Builder $u) => $u->where('blocked', false)));
    }

    /** Each post is listed at most once per language, plus once without one. */
    private static function postsPerPart(): int
    {
        return max(1, intdiv(config('seo.sitemap_urls'), Language::enabled()->count() + 1));
    }

    /** @param  list<array{loc: string, lastmod: mixed, alternates: array<string, string>}>  $urls */
    private function urlset(array $urls): Response
    {
        return $this->xml(view('seo.sitemap', ['urls' => $urls])->render());
    }

    private function xml(string $body): Response
    {
        // The declaration is added here, not in the view: where PHP's short_open_tag is on (SiteGround),
        // "<?xml" in a compiled view is read as PHP and the page fails.
        return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".ltrim($body))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
