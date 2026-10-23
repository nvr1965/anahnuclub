<?php

namespace Tests\Feature;

use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\User;
use App\Support\LinkPreviewFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = <<<'HTML'
        <html><head><title>Plain title</title>
        <meta property="og:title" content="Марокко: министр уволена через два дня">
        <meta property="og:description" content="Разногласия по поводу &amp; прочего">
        <meta property="og:image" content="/img/cover.jpg">
        <meta property="og:site_name" content="NofInfo">
        </head><body></body></html>
        HTML;

    public function test_a_post_with_a_link_shows_the_page_as_a_card()
    {
        Http::fake(['http://93.184.216.34/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html; charset=utf-8'])]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['body' => 'Смотрите http://93.184.216.34/news?id=176361.'])->assertRedirect();

        $post = Post::latest('id')->first();
        $this->assertSame('http://93.184.216.34/news?id=176361', $post->link_url);
        $this->get("/posts/{$post->id}")->assertInertia(fn ($page) => $page
            ->where('post.data.link.title', 'Марокко: министр уволена через два дня')
            ->where('post.data.link.description', 'Разногласия по поводу & прочего')
            ->where('post.data.link.image', 'http://93.184.216.34/img/cover.jpg')
            ->where('post.data.link.domain', '93.184.216.34'));
    }

    public function test_a_page_without_a_preview_picture_shows_the_picture_of_its_article()
    {
        $fetcher = new LinkPreviewFetcher;
        $page = fn (string $body) => $fetcher->parse("<html><head><title>T</title></head><body>{$body}</body></html>", 'https://nofinfo.com/?id=1')['image'];

        $this->assertSame('https://nofinfo.com/img/photo.jpg', $page(
            '<img src="/logo.png"><article><img src="/icons/share.png"><img src="/img/tiny.jpg" width="40"><img src="/img/photo.jpg"></article>'
        ));
        $this->assertSame('https://cdn.example.com/a.jpg', $page('<main><img data-src="https://cdn.example.com/a.jpg"></main>'));
        $glued = $fetcher->parse('<html><head><meta property="og:image" content="https://nofinfo.com/ https://images.panet.com/a/1.jpg"></head></html>', 'https://nofinfo.com/?id=1');
        $this->assertSame('https://images.panet.com/a/1.jpg', $glued['image']);
        // Pictures outside the article belong to other news, not to this page.
        $this->assertNull($page('<aside><img src="/img/other-news.jpg"></aside><article><p>Только текст</p></article>'));
    }

    public function test_a_card_without_a_picture_is_read_again_after_an_hour()
    {
        Http::fake(['http://93.184.216.34/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);
        $card = LinkPreview::create(['url' => 'http://93.184.216.34/news', 'title' => 'Без фото']);
        $this->actingAs(User::factory()->create());

        $this->getJson('/link-preview?url=http://93.184.216.34/news')->assertJsonPath('link.image', null);
        Http::assertNothingSent();

        $card->forceFill(['updated_at' => now()->subHours(2)])->saveQuietly();
        $this->getJson('/link-preview?url=http://93.184.216.34/news')->assertJsonPath('link.image', 'http://93.184.216.34/img/cover.jpg');
    }

    public function test_private_addresses_are_never_fetched()
    {
        Http::fake();
        foreach (['http://127.0.0.1/', 'http://192.168.50.217/feed', 'http://10.0.0.5/', 'http://[::1]/', 'file:///etc/passwd', 'http://169.254.169.254/latest'] as $url) {
            $this->assertFalse(LinkPreviewFetcher::isPublic($url), $url);
            $this->assertNull((new LinkPreviewFetcher)->fetch($url));
        }
        Http::assertNothingSent();
    }

    public function test_a_redirect_to_a_private_address_is_not_followed()
    {
        Http::fake(['http://93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);

        $this->assertNull((new LinkPreviewFetcher)->fetch('http://93.184.216.34/go'));
        Http::assertSentCount(1);
    }

    public function test_an_unreadable_page_is_remembered_and_shows_a_small_card_of_its_site()
    {
        Http::fake(['http://93.184.216.34/*' => Http::response('nope', 404)]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['body' => 'http://93.184.216.34/missing']);

        $this->assertDatabaseHas('link_previews', ['url' => 'http://93.184.216.34/missing', 'title' => null]);
        $post = Post::latest('id')->first();
        $this->get("/posts/{$post->id}")->assertInertia(fn ($page) => $page
            ->where('post.data.link.domain', '93.184.216.34')
            ->where('post.data.link.title', null)
            ->where('post.data.link.image', null));
    }

    public function test_older_posts_get_their_links_found()
    {
        Http::fake(['http://93.184.216.34/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);
        $user = User::factory()->create();
        $id = Post::create(['user_id' => $user->id, 'type' => 'status', 'body' => 'старый'])->id;
        Post::whereKey($id)->update(['body' => '<a href="http://93.184.216.34/old">ссылка</a>', 'link_url' => null]);

        $this->artisan('posts:link-previews')->assertSuccessful();

        $this->assertSame('http://93.184.216.34/old', Post::find($id)->link_url);
        $this->assertSame('NofInfo', LinkPreview::where('url', 'http://93.184.216.34/old')->value('site_name'));
    }

    public function test_one_page_can_be_read_by_hand_to_see_why_it_fails()
    {
        Http::fake(['http://93.184.216.34/blocked' => Http::response('no', 403, ['Content-Type' => 'text/html']), 'http://93.184.216.34/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);

        $this->artisan('posts:link-previews', ['--url' => 'http://93.184.216.34/blocked'])->expectsOutputToContain('HTTP 403')->assertSuccessful();
        $this->artisan('posts:link-previews', ['--url' => 'http://93.184.216.34/ok'])->expectsOutputToContain('site_name: NofInfo')->assertSuccessful();
    }

    public function test_the_card_shows_while_writing_and_can_be_closed()
    {
        Http::fake(['http://93.184.216.34/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/link-preview?url='.urlencode('Глядите http://93.184.216.34/item/1'))
            ->assertOk()->assertJsonPath('link.title', 'Марокко: министр уволена через два дня')->assertJsonPath('link.domain', '93.184.216.34');
        $this->getJson('/link-preview?url=nothing')->assertOk()->assertExactJson(['link' => null]);

        $this->post('/posts', ['body' => 'http://93.184.216.34/item/1', 'no_link_preview' => true])->assertRedirect();
        $post = Post::latest('id')->first();
        $this->assertSame('', $post->link_url);
        $this->get("/posts/{$post->id}")->assertInertia(fn ($page) => $page->where('post.data.link', null));
    }

    public function test_a_site_that_turns_browsers_away_is_read_as_facebooks_crawler()
    {
        Http::fake(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'facebookexternalhit')
            ? Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])
            : Http::response('Forbidden', 403, ['Content-Type' => 'text/html']));

        $found = (new LinkPreviewFetcher)->fetch('http://93.184.216.34/item/270818#0_8_11718');

        $this->assertSame('Марокко: министр уволена через два дня', $found['title']);
        // The part after # is not sent.
        Http::assertSent(fn ($request) => $request->url() === 'http://93.184.216.34/item/270818');
    }

    public function test_a_site_that_turns_this_server_away_is_read_through_the_outside_reader()
    {
        config(['services.link_preview.fallback' => 'https://reader.example/']);
        Http::fake([
            'http://93.184.216.34/*' => Http::response('Forbidden', 403, ['Content-Type' => 'text/html']),
            'https://reader.example/*' => Http::response(['status' => 'success', 'data' => [
                'title' => 'Новости Израиля', 'description' => 'Статья', 'image' => ['url' => 'https://cdn.example/a.jpg'], 'publisher' => 'IsraNews',
            ]]),
        ]);

        $fetcher = new LinkPreviewFetcher;
        $this->assertSame(['title' => 'Новости Израиля', 'description' => 'Статья', 'image' => 'https://cdn.example/a.jpg', 'site_name' => 'IsraNews'], $fetcher->fetch('http://93.184.216.34/item/1'));

        config(['services.link_preview.fallback' => '']);
        $this->assertNull($fetcher->fetch('http://93.184.216.34/item/1'));
        $this->assertStringContainsString('HTTP 403', $fetcher->lastError);
    }

    public function test_a_page_that_could_not_be_read_is_tried_again_by_the_hourly_retry()
    {
        $user = User::factory()->create();
        $up = false;
        Http::fake(function () use (&$up) {
            return $up ? Http::response(self::PAGE, 200, ['Content-Type' => 'text/html']) : Http::response('down', 503, ['Content-Type' => 'text/html']);
        });
        $this->actingAs($user)->post('/posts', ['body' => 'http://93.184.216.34/item/5'])->assertRedirect();
        $this->assertNull(LinkPreview::firstOrFail()->title);

        $this->travel(31)->minutes();
        $up = true;
        $this->artisan('posts:link-previews --days=2')->assertSuccessful();
        $this->assertSame('Марокко: министр уволена через два дня', LinkPreview::firstOrFail()->title);
    }
}
