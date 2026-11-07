<?php

namespace Tests\Feature;

use App\Feeds\FeedCollector;
use App\Feeds\FeedPublisher;
use App\Feeds\FeedReader;
use App\Models\FeedChannel;
use App\Models\FeedItem;
use App\Http\Controllers\FriendController;
use App\Models\FeedSource;
use App\Models\Group;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ClubAuthorInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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

    /** The moment of the author's planned post number $n today. */
    private function atSlot(FeedChannel $channel, int $n, int $plusMinutes = 0): void
    {
        [$h, $m] = explode(':', FeedPublisher::plan($channel)[$n]);
        Carbon::setTestNow(Carbon::parse('2026-11-08', 'Asia/Jerusalem')->setTime((int) $h, (int) $m)->addMinutes($plusMinutes)->utc());
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
        $this->atSlot($channel, 0);

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
        $this->atSlot($channel, 1);

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
        $this->atSlot($channel, 0);

        $this->assertNotNull($publisher->publishNext());
        Carbon::setTestNow(now()->addHours(5));
        $this->assertNull($publisher->publishNext());
        $this->assertSame(1, Post::count());
    }

    public function test_requests_to_a_club_author_go_to_the_club_inbox_and_its_curator_answers()
    {
        Notification::fake();
        $channel = $this->author();
        $author = $channel->user;
        $anna = User::factory()->create(['name' => 'Анна']);
        $admin = User::factory()->create(['is_admin' => true]);

        // Asked to be friends: a letter to club@anahnu.club; messages stay shut, and it is not suggested.
        $this->actingAs($anna)->post("/people/{$author->id}/friend")->assertRedirect();
        Notification::assertSentOnDemand(ClubAuthorInvited::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'club@anahnu.club' && $n->from->is($anna) && ! $n->group);
        $this->assertFalse($anna->canMessage($author));
        $this->assertNotContains($author->id, FriendController::suggestionsFor(User::factory()->create(), 60)->pluck('id')->all());

        // Invited into a group.
        $group = Group::create(['owner_id' => $anna->id, 'name' => 'Север', 'slug' => 'sever']);
        $group->members()->attach($anna->id, ['role' => 'owner', 'approved' => true]);
        $this->actingAs($anna)->post("/groups/{$group->slug}/invite", ['user_ids' => [$author->id]])->assertRedirect();
        Notification::assertSentOnDemand(ClubAuthorInvited::class, fn ($n) => $n->group?->is($group));

        // The curator sees both on the admin page and accepts them.
        $this->actingAs($admin)->get('/admin/feeds?tab=authors')->assertInertia(fn ($page) => $page
            ->where('channels.0.requests.0.name', 'Анна')->where('channels.0.invitations.0.group', 'Север'));
        $this->post("/admin/feeds/authors/{$channel->id}/friends/{$anna->id}", ['accept' => true]);
        $this->post("/admin/feeds/authors/{$channel->id}/invitations/{$group->invitations()->first()->id}", ['accept' => true]);

        $this->assertSame('friends', $anna->friendshipWith($author));
        $this->assertTrue($group->members()->whereKey($author->id)->exists());
    }

    public function test_a_range_of_posts_a_day_gives_a_steady_number_each_day()
    {
        $channel = $this->author(['daily_min' => 1, 'daily_limit' => 2]);
        $days = collect(range(1, 30))->map(fn ($d) => $channel->limitFor('2026-12-'.str_pad($d, 2, '0', STR_PAD_LEFT)));

        $this->assertSame([1, 2], $days->unique()->sort()->values()->all());
        $this->assertSame($channel->limitFor('2026-12-05'), $channel->limitFor('2026-12-05'));
        $this->assertSame(3, $this->author(['daily_limit' => 3])->limitFor('2026-12-05'));
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
        // Each author's name and profile line are in its own language.
        $this->assertSame('علي', User::where('email', 'ali@authors.anahnu.club')->value('name'));
        $this->assertStringContainsString('Autor bei AnahnuClub', User::where('email', 'dietrich@authors.anahnu.club')->value('about'));
        $this->assertStringContainsString('автор AnahnuClub', $farik->about);
        $this->assertContains('club-author', \App\Support\Badges::special($farik->fresh()));
        $this->assertGreaterThan(0, $farik->feedChannel()->first()->sources()->count());

        // Topic groups: Фарик and Айгуль write in "Средняя Азия", Сергей in "Новости России"; made once.
        $asia = Group::firstWhere('slug', 'central-asia');
        $this->assertSame('Средняя Азия', $asia->name);
        $this->assertSame($asia->id, $farik->feedChannel()->first()->group_id);
        $this->assertSame($asia->id, User::where('email', 'aigul@authors.anahnu.club')->first()->feedChannel()->first()->group_id);
        $this->assertSame('owner', $asia->roleOf($farik));
        $this->assertSame('Новости России', User::where('email', 'sergey@authors.anahnu.club')->first()->feedChannel()->first()->group->name);
        $this->assertSame(1, Group::where('slug', 'central-asia')->count());
        // Addresses that never worked are switched off; Telegram channels and Panet's front page are sources.
        $this->assertTrue(FeedSource::where('url', 'https://t.me/s/bokranews')->exists());
        $this->assertFalse(FeedSource::where('url', 'https://www.panet.co.il/rss/news')->where('active', true)->exists());
    }

    public function test_an_author_in_a_topic_group_posts_there_and_still_to_everyone()
    {
        $channel = $this->author();
        $group = Group::create(['owner_id' => $channel->user_id, 'name' => 'Средняя Азия', 'slug' => 'central-asia']);
        $channel->update(['group_id' => $group->id]);
        FeedItem::create(['feed_channel_id' => $channel->id, 'summary' => 'Новость', 'status' => 'approved']);
        $this->atSlot($channel, 0);

        $item = app(FeedPublisher::class)->publishNext();

        $post = Post::find($item->post_id);
        $this->assertSame($group->id, $post->group_id);
        $this->assertSame('public', $post->audience);
    }

    public function test_each_author_has_its_own_times_spread_over_the_day()
    {
        $channel = $this->author(['daily_limit' => 3]);
        $other = $this->author(['daily_limit' => 3]);
        $day = 16 * 60;

        $slots = $channel->slotsFor('2026-11-08', 3, $day);
        // One post in each third of the day, never at its very edges.
        foreach ($slots as $i => $minute) {
            $this->assertGreaterThanOrEqual($i * $day / 3, $minute);
            $this->assertLessThan(($i + 1) * $day / 3, $minute);
        }
        $this->assertSame($slots, $channel->slotsFor('2026-11-08', 3, $day));
        $this->assertNotSame($slots, $channel->slotsFor('2026-11-09', 3, $day));
        $this->assertNotSame($slots, $other->slotsFor('2026-11-08', 3, $day));

        // Before its first time the author waits, then posts.
        FeedItem::create(['feed_channel_id' => $channel->id, 'summary' => 'Новость', 'status' => 'approved']);
        $publisher = app(FeedPublisher::class);
        $this->atSlot($channel, 0, -1);
        $this->assertNull($publisher->publishNext());
        $this->atSlot($channel, 0);
        $this->assertNotNull($publisher->publishNext());
    }

    public function test_the_pause_between_posts_of_the_site_changes()
    {
        $gaps = collect(range(1, 50))->map(fn ($id) => FeedPublisher::siteGap($id));

        $this->assertGreaterThanOrEqual(10, $gaps->min());
        $this->assertLessThanOrEqual(25, $gaps->max());
        $this->assertGreaterThan(5, $gaps->unique()->count());
    }

    public function test_telegram_channels_and_sites_without_rss_are_read_from_their_pages()
    {
        $this->assertSame('telegram', \App\Feeds\PageParsers::kind('https://t.me/kunuzru'));
        $this->assertSame('https://t.me/s/kunuzru', \App\Feeds\PageParsers::telegramUrl('https://t.me/kunuzru'));
        $this->assertSame('https://t.me/s/kunuzru', \App\Feeds\PageParsers::telegramUrl('@kunuzru'));
        $this->assertSame('panet', \App\Feeds\PageParsers::kind('https://panet.com/'));
        $this->assertSame('feed', \App\Feeds\PageParsers::kind('https://www.panet.co.il/rss/news'));
        $this->assertSame('bokra', \App\Feeds\PageParsers::kind('https://bokra.net/'));

        $post = fn ($n, $text, $extra = '') => '<div class="tgme_widget_message_bubble">'.$extra
            .'<div class="tgme_widget_message_text js-message_text" dir="auto">'.$text.'</div>'
            .'<a class="tgme_widget_message_date" href="https://t.me/kunuzru/'.$n.'"><time datetime="'.now()->subHour()->toIso8601String().'" class="time">9:00</time></a></div>';
        Http::fake([
            't.me/*' => Http::response('<html>'
                .$post(1, 'Старое: в Ташкенте прошёл дождь, впервые за месяц.')
                .$post(2, 'Коротко')
                .$post(3, 'В Самарканде открыли новый музей.<br>Подробности на сайте <a href="https://kun.uz/1">kun.uz</a>', '<a class="tgme_widget_message_photo_wrap" style="width:800px;background-image:url(\'https://cdn.tg/photo.jpg\')"></a>')
                .'</html>'),
            'panet.com/story/*' => Http::response('<html><head><meta property="og:image" content="https://panet.com/p.jpg"></head><div id="story-subject"><h3>عنوان الخبر</h3></div><div id="story-intro">مقدمة الخبر</div></html>'),
            'panet.com/*' => Http::response('<a href="https://panet.com/story/123">x</a><a href="https://www.panet.com/story/124">y</a>'),
        ]);
        $reader = app(FeedReader::class);

        $items = $reader->read('https://t.me/kunuzru');
        $this->assertCount(2, $items);
        $this->assertSame('https://t.me/kunuzru/3', $items[0]['url']);
        $this->assertSame('В Самарканде открыли новый музей.', $items[0]['title']);
        $this->assertStringContainsString('Подробности на сайте kun.uz', $items[0]['summary']);
        $this->assertSame('https://cdn.tg/photo.jpg', $items[0]['image']);
        $this->assertNotNull($items[0]['published_at']);

        $items = $reader->read('https://panet.com/');
        $this->assertCount(2, $items);
        $this->assertSame('عنوان الخبر', $items[0]['title']);
        $this->assertSame('مقدمة الخبر', $items[0]['summary']);
        $this->assertSame('https://panet.com/p.jpg', $items[0]['image']);
        $this->assertSame('https://panet.com/story/123', $items[0]['url']);
    }
}
