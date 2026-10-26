<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $post = Post::create(['user_id' => User::factory()->create()->id, 'body' => 'Привет']);
        $url = route('posts.show', $post);

        $this->get("/posts/{$post->id}?lang=he&utm_source=fb")
            ->assertSee('<link rel="canonical" href="'.$url.'?lang=he">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="'.$url.'?lang=ru">', false)
            ->assertSee('<link rel="alternate" hreflang="ar" href="'.$url.'?lang=ar">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$url.'">', false)
            ->assertSee('<html translate="no" lang="he" dir="rtl">', false);

        $this->get('/')->assertSee('<link rel="canonical" href="'.url('/').'/">', false);
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

    public function test_the_sitemap_lists_public_posts_and_pages_in_every_language()
    {
        $author = User::factory()->create();
        $public = Post::create(['user_id' => $author->id, 'body' => 'Всем']);
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
            ->assertSee('<loc>'.route('posts.show', $public).'?lang=ar</loc>', false)
            ->assertSee('hreflang="x-default" href="'.route('posts.show', $public).'"', false)
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
}
