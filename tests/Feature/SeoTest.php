<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use App\Support\SiteMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['seo.indexable' => true]);
    }

    public function test_each_page_names_its_address_in_every_language()
    {
        $this->get('/register?lang=he&utm_source=fb')
            ->assertSee('<link rel="canonical" href="'.route('register').'?lang=he">', false)
            ->assertSee('<link rel="alternate" hreflang="ar" href="'.route('register').'?lang=ar">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.route('register').'">', false)
            ->assertSee('<html translate="no" lang="he" dir="rtl">', false);

        $this->get('/')->assertSee('<link rel="canonical" href="'.url('/').'/">', false);
    }

    public function test_a_post_names_only_the_languages_its_text_is_in()
    {
        $post = Post::create(['user_id' => User::factory()->create()->id, 'body' => 'Привет', 'locale' => 'ru']);
        $this->translate($post, 'he');
        $this->translate($post, 'ar', 'an older text');
        $url = route('posts.show', $post);

        $this->get("/posts/{$post->id}?lang=he&utm_source=fb")
            ->assertSee('<link rel="canonical" href="'.$url.'?lang=he">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="'.$url.'?lang=ru">', false)
            ->assertSee('<link rel="alternate" hreflang="he" href="'.$url.'?lang=he">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$url.'">', false)
            // Arabic is the translation of an older version of the text; English there is none.
            ->assertDontSee('hreflang="ar"', false)
            ->assertDontSee('hreflang="en"', false)
            ->assertSee('<html translate="no" lang="he" dir="rtl">', false);

        // The post under a menu in another language is the same text: it points to the plain address.
        $this->get("/posts/{$post->id}?lang=en")->assertSee('<link rel="canonical" href="'.$url.'">', false);
    }

    public function test_pages_tell_search_engines_what_they_are()
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $this->get('/')->assertSee('"@type":"Organization"', false)->assertSee('"@type":"WebSite"', false);

        $post = Post::create(['user_id' => $anna->id, 'body' => '<p>Встречаемся</p><p>&lt;/script&gt;</p>', 'locale' => 'ru']);
        $this->get("/posts/{$post->id}")
            ->assertSee('<script type="application/ld+json">', false)
            ->assertSee('"@type":"SocialMediaPosting"', false)
            ->assertSee('"author":{"@type":"Person","name":"Анна"}', false)
            ->assertSee('"inLanguage":"ru"', false)
            ->assertDontSee('"@type":"Event"', false);
        // Text written by members never ends the script early.
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $this->get("/posts/{$post->id}")->getContent(), $script);
        $this->assertStringContainsString('\u003C/script\u003E', $script[1]);
        $this->assertSame('SocialMediaPosting', json_decode($script[1], true)['@graph'][0]['@type']);

        $event = Event::create(['user_id' => $anna->id, 'title' => 'Шаббат в парке', 'location' => 'Парк Хайфы', 'starts_at' => now()->addWeek()]);
        $invite = Post::create(['user_id' => $anna->id, 'type' => 'event', 'body' => '', 'event_id' => $event->id]);
        $this->get("/posts/{$invite->id}")
            ->assertSee('"@type":"Event"', false)
            ->assertSee('"name":"Шаббат в парке"', false)
            ->assertSee('"location":{"@type":"Place","name":"Парк Хайфы","address":"Парк Хайфы"}', false)
            ->assertSee('"startDate":"'.$event->starts_at->toAtomString().'"', false);
    }

    public function test_new_public_posts_are_announced_to_indexnow()
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);
        $anna = User::factory()->create();
        $key = SiteMeta::indexNowKey();

        $this->get("/$key.txt")->assertOk()->assertContent($key);
        $this->get('/0123456789abcdef.txt')->assertNotFound();

        $this->actingAs($anna)->post('/posts', ['body' => 'Всем привет'])->assertRedirect();
        $post = Post::latest('id')->first();
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request['key'] === $key && $request['host'] === parse_url(url('/'), PHP_URL_HOST)
            && in_array(route('posts.show', $post), $request['urlList'], true));

        // Posts for friends only, and a test copy of the site, announce nothing.
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);
        $this->post('/posts', ['body' => 'Друзьям', 'audience' => 'friends']);
        config(['seo.indexable' => false]);
        $this->post('/posts', ['body' => 'Тест']);
        Http::assertNothingSent();
        $this->get("/$key.txt")->assertNotFound();
    }

    public function test_guests_can_page_through_all_public_posts()
    {
        $author = User::factory()->create();
        $oldest = Post::create(['user_id' => $author->id, 'body' => 'Самый первый', 'created_at' => now()->subYear()]);
        foreach (range(1, 50) as $n) {
            Post::create(['user_id' => $author->id, 'body' => "Пост $n", 'created_at' => now()->subDays(60 - $n)]);
        }
        $friends = Post::create(['user_id' => $author->id, 'body' => 'Друзьям', 'audience' => 'friends']);

        $this->get('/')->assertSee('href="'.route('posts.archive').'"', false);
        $this->get('/posts')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('posts.archive').'">', false)
            ->assertSee('href="'.route('posts.archive.page', 2).'"', false)
            ->assertDontSee('href="'.route('posts.show', $oldest).'"', false)
            ->assertDontSee('href="'.route('posts.show', $friends).'"', false);
        $this->get('/posts/page/2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('posts.archive.page', 2).'">', false)
            ->assertSee('href="'.route('posts.show', $oldest).'"', false)
            ->assertSee('href="'.route('posts.archive').'"', false);
        $this->get('/posts/page/1')->assertRedirect(route('posts.archive'))->assertStatus(301);
        $this->get('/posts/page/3')->assertNotFound();
        $this->get('/sitemap-pages.xml')->assertSee('<loc>'.route('posts.archive').'?lang=he</loc>', false);

        $this->actingAs($author)->get('/posts')->assertRedirect(route('dashboard'));
    }

    public function test_pages_carry_their_title_and_text_without_javascript()
    {
        $post = Post::create(['user_id' => User::factory()->create(['name' => 'Анна'])->id, 'body' => '<p>Шаббат в парке в пятницу</p>']);

        $this->get("/posts/{$post->id}")
            ->assertSee('<title inertia>Анна: Шаббат в парке в пятницу</title>', false)
            ->assertSee('<h1>Анна: Шаббат в парке в пятницу</h1>', false)
            ->assertSee('<p>Шаббат в парке в пятницу</p>', false);

        // The home page links its latest posts, so search engines find them.
        $this->get('/')->assertSee('href="'.route('posts.show', $post).'"', false);

        $page = Page::create(['slug' => 'about', 'published' => true, 'content' => ['ru' => ['title' => 'О клубе', 'body' => '<p>Мы клуб</p>']]]);
        $this->get('/p/about?lang=ru')->assertSee('<title inertia>О клубе - anahnu.club</title>', false)
            ->assertSee('<meta name="description" content="Мы клуб">', false);
    }

    public function test_sign_in_forms_and_test_copies_stay_out_of_search()
    {
        $this->get('/login')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/register')->assertDontSee('noindex', false);

        config(['seo.indexable' => false]);
        $this->get('/register')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);
    }

    public function test_robots_txt_points_to_the_sitemap_and_keeps_private_parts_out()
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /messages')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_the_sitemap_lists_pages_in_every_language_and_posts_in_theirs()
    {
        $author = User::factory()->create();
        $public = Post::create(['user_id' => $author->id, 'body' => 'Всем', 'locale' => 'ru']);
        $this->translate($public, 'en');
        $this->translate($public, 'he', 'an older text');
        $photos = Post::create(['user_id' => $author->id, 'type' => 'photo', 'body' => '']);
        $friends = Post::create(['user_id' => $author->id, 'body' => 'Друзьям', 'audience' => 'friends']);
        $secret = Group::create(['name' => 'Secret', 'slug' => 'secret', 'is_private' => true]);
        $hidden = Post::create(['user_id' => $author->id, 'group_id' => $secret->id, 'body' => 'Своим']);
        Page::create(['slug' => 'club-rules', 'published' => true, 'content' => ['ru' => ['title' => 'Правила', 'body' => '…']]]);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>'."\n<sitemapindex", $this->get('/sitemap.xml')->getContent());
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee(route('sitemap.pages'), false)
            ->assertSee(route('sitemap.posts', 1), false);

        $this->get('/sitemap-pages.xml')->assertOk()
            ->assertSee('<loc>'.route('pages.show', 'club-rules').'?lang=he</loc>', false)
            ->assertSee('<loc>'.url('/').'/</loc>', false);

        $this->get('/sitemap-posts-1.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('posts.show', $public).'?lang=ru</loc>', false)
            ->assertSee('<loc>'.route('posts.show', $public).'?lang=en</loc>', false)
            ->assertDontSee(route('posts.show', $public).'?lang=he', false)
            ->assertDontSee(route('posts.show', $public).'?lang=ar', false)
            ->assertSee('hreflang="x-default" href="'.route('posts.show', $public).'"', false)
            // A post without words is listed once, at its plain address.
            ->assertSee('<loc>'.route('posts.show', $photos).'</loc>', false)
            ->assertDontSee(route('posts.show', $photos).'?lang=', false)
            ->assertDontSee(route('posts.show', $friends).'<', false)
            ->assertDontSee(route('posts.show', $hidden).'<', false);
    }

    public function test_old_site_addresses_lead_to_the_same_content_here()
    {
        $member = User::factory()->create(['username' => 'ivan']);
        $group = Group::create(['name' => 'Хайфа', 'slug' => 'haifa']);
        $discussion = Post::create(['user_id' => $member->id, 'group_id' => $group->id, 'body' => 'Тема', 'legacy_source' => 'discussion', 'legacy_id' => 7]);
        $wall = Post::create(['user_id' => $member->id, 'body' => 'Стена', 'legacy_source' => 'activity', 'legacy_id' => 55]);
        $video = Video::create(['user_id' => $member->id, 'title' => 'Клип', 'provider' => 'youtube', 'provider_id' => 'x', 'url' => 'https://youtu.be/x', 'legacy_source' => 'jomsocial_video', 'legacy_id' => 9]);

        $this->get("/community/groups/viewgroup/{$group->id}-haifa.html")->assertRedirect(route('groups.show', $group))->assertStatus(301);
        $this->get('/community/groups/viewdiscussion/7-tema.html')->assertRedirect(route('posts.show', $discussion));
        $this->get('/index.php?option=com_community&view=groups&task=viewdiscussion&topicid=7')->assertRedirect(route('posts.show', $discussion));
        $this->get('/community/profile.html?actid=55')->assertRedirect(route('posts.show', $wall));
        $this->get('/community/videos/video/9-klip.html')->assertRedirect(route('videos.show', $video));
        $this->get("/community/{$member->id}-ivan/profile.html")->assertRedirect(route('people.show', $member));
        $this->get("/he/community/{$member->id}-ivan.html")->assertRedirect(route('people.show', $member).'?lang=he');

        // The old site's menu was "jomsocial"; a member's part after it counts whatever their name.
        $this->get("/en/jomsocial/{$member->id}-someone-else/events.html")->assertRedirect(route('people.show', $member).'?lang=en');

        // An old article that only looks like a member's address, and old pages with nothing here,
        // are gone (410): search engines drop them, where a redirect home counted as a soft 404.
        $this->get("/news/{$member->id}-about-us.html")->assertStatus(410)->assertSee(route('home'), false);
        $this->get('/index.php?option=com_content&view=article&id=3')->assertStatus(410);
        $this->get('/jomsocial/999-gone/profile.html')->assertStatus(410);

        // There are no feeds here: old ones are gone too, even when they name a group that exists.
        $this->get("/jomsocial/events.feed?groupid={$group->id}")->assertStatus(410);
        $this->get('/jomsocial/groups/viewdiscussion/7-tema.feed')->assertStatus(410);

        // Addresses that were never the old site's are simply not found.
        $this->get('/no-such-page')->assertNotFound();
    }

    /** A translation of the post's current text, or of another one (then it is out of date). */
    private function translate(Post $post, string $locale, ?string $of = null): void
    {
        $post->contentTranslations()->create(['field' => 'body', 'locale' => $locale, 'text' => "[$locale]", 'provider' => 'test',
            'source_hash' => hash('sha256', $of ?? $post->body)]);
    }
}
