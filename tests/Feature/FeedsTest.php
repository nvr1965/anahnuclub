<?php

namespace Tests\Feature;

use App\Feeds\FeedCollector;
use App\Feeds\FeedPublisher;
use App\Feeds\FeedReader;
use App\Models\FeedChannel;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeedsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // 10:00 in Israel: inside the posting window.
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00', 'Asia/Jerusalem')->utc());
    }

    private function author(array $channel = []): FeedChannel
    {
        $user = User::factory()->create(['name' => 'Фарик', 'locale' => 'ru']);

        return FeedChannel::create($channel + ['user_id' => $user->id, 'topic' => 'Узбекистан', 'daily_limit' => 2]);
    }

    private function rss(array $items): string
    {
        $xml = '<?xml version="1.0"?><rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel><title>Gazeta</title>';
        foreach ($items as $i) {
            $xml .= '<item><title>'.$i['title'].'</title><link>'.$i['link'].'</link>'
                .'<description><![CDATA['.($i['description'] ?? '').']]></description>'
                .'<pubDate>'.($i['date'] ?? now()->subHour()->toRssString()).'</pubDate>'
                .(isset($i['image']) ? '<media:content url="'.$i['image'].'" medium="image"/>' : '')
                .'</item>';
        }

        return $xml.'</channel></rss>';
    }

    public function test_rss_and_atom_feeds_become_items_with_a_short_excerpt()
    {
        $reader = new FeedReader;
        $items = $reader->parse($this->rss([[
            'title' => 'Ташкент &amp; метро', 'link' => 'https://www.gazeta.uz/ru/2026/11/08/metro/',
            'description' => '<p>'.str_repeat('Очень длинный текст новости. ', 40).'</p>', 'image' => 'https://img.gazeta.uz/a.jpg',
        ]]));
        $this->assertSame('Ташкент & метро', $items[0]['title']);
        $this->assertSame('https://img.gazeta.uz/a.jpg', $items[0]['image']);
        $this->assertLessThanOrEqual(FeedReader::SUMMARY_LENGTH + 1, mb_strlen($items[0]['summary']));
        $this->assertStringEndsWith('…', $items[0]['summary']);

        $atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Atom news</title>'
            .'<link rel="alternate" href="https://example.com/a"/><summary>Short</summary><updated>2026-11-08T07:00:00Z</updated></entry></feed>';
        $items = $reader->parse($atom);
        $this->assertSame('https://example.com/a', $items[0]['url']);
        $this->assertSame('Short', $items[0]['summary']);
    }

    public function test_each_news_item_is_taken_once_fresh_and_without_stop_words()
    {
        $channel = $this->author(['stop_words' => "казино\nреклама"]);
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Gazeta.uz', 'url' => 'https://gazeta.uz/rss']);
        Http::fake(['gazeta.uz/*' => Http::response($this->rss([
            ['title' => 'Новое метро', 'link' => 'https://gazeta.uz/a?utm_source=rss'],
            ['title' => 'Новое метро!', 'link' => 'https://kun.uz/other'],           // the same story elsewhere
            ['title' => 'Старая новость', 'link' => 'https://gazeta.uz/old', 'date' => now()->subDays(3)->toRssString()],
            ['title' => 'Открылось казино', 'link' => 'https://gazeta.uz/casino'],
        ]))]);

        $this->assertSame(1, app(FeedCollector::class)->collect($source));
        $this->assertSame(0, app(FeedCollector::class)->collect($source), 'read again, nothing new');

        $this->assertSame(['Новое метро'], FeedItem::where('status', 'new')->pluck('title')->all());
        $this->assertSame('stop word: казино', FeedItem::where('status', 'skipped')->value('reason'));
        $this->assertNull($source->fresh()->error);

        // The same page under another tracking parameter is the same item.
        app(FeedCollector::class)->store($source, [['title' => 'Другой заголовок', 'url' => 'https://www.gazeta.uz/a/?utm_medium=x', 'summary' => null, 'image' => null, 'published_at' => now()]]);
        $this->assertSame(2, FeedItem::count());
    }

    public function test_a_source_that_does_not_answer_keeps_its_error()
    {
        $channel = $this->author();
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Down', 'url' => 'https://down.example/rss']);
        Http::fake(['down.example/*' => Http::response('nope', 503)]);

        app(FeedCollector::class)->collect($source);
        $this->assertSame('HTTP 503', $source->fresh()->error);
    }

    public function test_trusted_sources_of_an_auto_approved_author_skip_the_curator()
    {
        $channel = $this->author(['auto_approve' => true]);
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Gazeta.uz', 'url' => 'https://gazeta.uz/rss', 'trusted' => true]);
        app(FeedCollector::class)->store($source, [['title' => 'Новость', 'url' => 'https://gazeta.uz/1', 'summary' => 'Коротко', 'image' => null, 'published_at' => now()]]);

        $this->assertSame('approved', FeedItem::first()->status);
    }

    public function test_an_approved_item_becomes_a_public_post_of_its_author_with_the_link_card()
    {
        $channel = $this->author();
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Gazeta.uz', 'url' => 'https://gazeta.uz/rss', 'locale' => 'ru']);
        FeedItem::create(['feed_channel_id' => $channel->id, 'feed_source_id' => $source->id, 'url' => 'https://gazeta.uz/1',
            'title' => 'Новое метро', 'summary' => 'В Ташкенте открыли станцию.', 'image' => 'https://img.gazeta.uz/1.jpg', 'status' => 'approved']);

        $item = app(FeedPublisher::class)->publishNext();

        $post = Post::find($item->post_id);
        $this->assertSame('posted', $item->status);
        $this->assertSame($channel->user_id, $post->user_id);
        $this->assertSame('public', $post->audience);
        $this->assertSame('https://gazeta.uz/1', $post->link_url);
        $this->assertStringContainsString('В Ташкенте открыли станцию.', $post->body);
        $this->assertStringContainsString('Gazeta.uz', $post->body);
        $this->assertSame('Новое метро', LinkPreview::where('url', 'https://gazeta.uz/1')->value('title'));
    }

    public function test_posts_are_spread_over_the_day_and_stop_at_night_or_on_pause()
    {
        $channel = $this->author(['daily_limit' => 10]);
        foreach (range(1, 3) as $i) {
            FeedItem::create(['feed_channel_id' => $channel->id, 'title' => "Новость $i", 'summary' => "Текст $i", 'status' => 'approved']);
        }
        $publisher = app(FeedPublisher::class);

        $this->assertNotNull($publisher->publishNext());
        // Not twice within minutes, and the same author waits an hour and a half.
        Carbon::setTestNow(now()->addMinutes(15));
        $this->assertNull($publisher->publishNext());
        Carbon::setTestNow(now()->addMinutes(80));
        $this->assertNotNull($publisher->publishNext());

        // At night nothing goes out.
        Carbon::setTestNow(Carbon::parse('2026-11-08 23:30', 'Asia/Jerusalem')->utc());
        $this->assertNull($publisher->publishNext());

        // Paused by the admin.
        Carbon::setTestNow(Carbon::parse('2026-11-08 20:00', 'Asia/Jerusalem')->utc());
        Setting::create(['key' => 'feeds_paused', 'value' => '1']);
        Cache::memo()->forget('settings');
        $this->assertNull($publisher->publishNext());
        $this->assertSame(2, Post::count());
    }

    public function test_an_author_posts_no_more_than_its_daily_number()
    {
        $channel = $this->author(['daily_limit' => 1]);
        FeedItem::create(['feed_channel_id' => $channel->id, 'summary' => 'Первый', 'status' => 'approved']);
        FeedItem::create(['feed_channel_id' => $channel->id, 'summary' => 'Второй', 'status' => 'approved']);
        $publisher = app(FeedPublisher::class);

        $this->assertNotNull($publisher->publishNext());
        Carbon::setTestNow(now()->addHours(5));
        $this->assertNull($publisher->publishNext());
        $this->assertSame(1, Post::count());
    }

    public function test_club_authors_take_no_friend_requests_or_messages_and_are_not_suggested()
    {
        $channel = $this->author();
        $anna = User::factory()->create();

        $this->actingAs($anna)->post("/people/{$channel->user_id}/friend")->assertStatus(422);
        $this->assertFalse($anna->canMessage($channel->user));
        $this->actingAs($anna)->get("/people/{$channel->user_id}")->assertInertia(fn ($page) => $page
            ->where('profile.friendship', null)->where('profile.can_message', false));
        $this->assertNotContains($channel->user_id, \App\Http\Controllers\FriendController::suggestionsFor($anna, 60)->pluck('id')->all());
    }

    public function test_the_admin_approves_skips_and_adds_items_by_hand()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $channel = $this->author();
        $item = FeedItem::create(['feed_channel_id' => $channel->id, 'title' => 'Новость', 'status' => 'new']);

        $this->actingAs($admin)->get('/admin/feeds')->assertInertia(fn ($page) => $page
            ->component('admin/feeds')->where('items.data.0.id', $item->id)->where('channels.0.waiting', 1));

        $this->post('/admin/feeds/items/status', ['ids' => [$item->id], 'status' => 'approved']);
        $this->assertSame('approved', $item->fresh()->status);

        $this->post('/admin/feeds/items', ['feed_channel_id' => $channel->id, 'text' => "Анекдот один\n---\nАнекдот два"]);
        $this->assertSame(['Анекдот один', 'Анекдот два'], FeedItem::whereNull('title')->orderBy('id')->pluck('summary')->all());

        $this->post("/admin/feeds/items/{$item->id}/publish");
        $this->assertSame('posted', $item->fresh()->status);

        $this->actingAs(User::factory()->create())->get('/admin/feeds')->assertForbidden();
    }

    public function test_setup_creates_the_authors_once()
    {
        $this->artisan('feeds:setup')->assertSuccessful();
        $count = FeedChannel::count();
        $this->artisan('feeds:setup')->assertSuccessful();

        $this->assertSame($count, FeedChannel::count());
        $farik = User::where('email', 'farik@authors.anahnu.club')->first();
        $this->assertSame('Фарик', $farik->name);
        $this->assertStringContainsString('автор AnahnuClub', $farik->about);
        $this->assertContains('club-author', \App\Support\Badges::special($farik->fresh()));
        $this->assertGreaterThan(0, $farik->feedChannel()->first()->sources()->count());
    }
}
