<?php

namespace Tests\Feature;

use App\Feeds\FeedCollector;
use App\Feeds\FeedPublisher;
use App\Feeds\FeedReader;
use App\Feeds\PageParsers;
use App\Http\Controllers\FriendController;
use App\Models\FeedChannel;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\Group;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ClubAuthorInvited;
use App\Support\Badges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // The authors' whole daily numbers; the cut for news has its own test.
        config(['feeds.frequency' => 1]);
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

        // Straight to the author's queue: the authors post by themselves.
        $this->assertSame(['Новое метро'], FeedItem::where('status', 'approved')->pluck('title')->all());
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

        $this->actingAs($admin)->get('/admin/feeds?status=new')->assertInertia(fn ($page) => $page
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
        Storage::fake('public');
        $this->artisan('feeds:setup')->assertSuccessful();
        $count = FeedChannel::count();
        $this->artisan('feeds:setup')->assertSuccessful();

        $this->assertSame($count, FeedChannel::count());
        $farik = User::where('email', 'farik@authors.anahnu.club')->first();
        $this->assertSame('Farik', $farik->name);
        // Each author's name and profile line are in its own language.
        $this->assertSame('علي', User::where('email', 'ali@authors.anahnu.club')->value('name'));
        $this->assertStringContainsString('Autor bei AnahnuClub', User::where('email', 'dietrich@authors.anahnu.club')->value('about'));
        $this->assertStringContainsString('AnahnuClub muallifi', $farik->about);
        $this->assertSame('Айгүл', User::where('email', 'aigul@authors.anahnu.club')->value('name'));
        $this->assertContains('club-author', Badges::special($farik->fresh()));
        $this->assertGreaterThan(0, $farik->feedChannel()->first()->sources()->count());

        // Topic groups, one language each: Farik writes in the Uzbek group, Гульнара in the Russian "Средняя Азия"; made once.
        $asia = Group::firstWhere('slug', 'central-asia');
        $this->assertSame('Средняя Азия', $asia->name);
        $this->assertSame('ru', $asia->locale);
        $gulnara = User::where('email', 'gulnara@authors.anahnu.club')->first();
        $this->assertSame($asia->id, $gulnara->feedChannel()->first()->group_id);
        $this->assertSame('owner', $asia->roleOf($gulnara));
        $this->assertSame('O‘zbekiston yangiliklari', $farik->feedChannel()->first()->group->name);
        $this->assertSame('Новости России', User::where('email', 'sergey@authors.anahnu.club')->first()->feedChannel()->first()->group->name);
        $this->assertSame(1, Group::where('slug', 'central-asia')->count());
        // Israel news in Russian (Марина) and in Hebrew (דוד) are two groups.
        $this->assertSame('Новости Израиля', User::where('email', 'marina@authors.anahnu.club')->first()->feedChannel()->first()->group->name);
        $this->assertSame('חדשות ישראל', User::where('email', 'david@authors.anahnu.club')->first()->feedChannel()->first()->group->name);
        // Light topics: Linda in "Cooking", Mia in "Beauty", Ron in "Tech & IT"; Изя's jokes whole.
        $linda = User::where('email', 'faina@authors.anahnu.club')->first();
        $this->assertSame(['Linda', 'en'], [$linda->name, $linda->locale]);
        $this->assertSame('Cooking', $linda->feedChannel()->first()->group->name);
        $this->assertSame('Beauty', User::where('email', 'mia@authors.anahnu.club')->first()->feedChannel()->first()->group->name);
        $this->assertSame('Tech & IT', User::where('email', 'ron@authors.anahnu.club')->first()->feedChannel()->first()->group->name);
        // Each club group has its picture.
        $this->assertSame(0, Group::whereIn('slug', ['central-asia', 'israel', 'israel-he', 'cooking'])->whereNull('avatar_path')->count());
        $this->assertTrue(FeedSource::where('url', 'https://www.anekdot.ru/rss/export_j.xml')->value('full_text'));
        // Addresses that never worked are switched off; Telegram channels and Panet's front page are sources.
        $this->assertTrue(FeedSource::where('url', 'https://t.me/s/bokranews')->exists());
        $this->assertFalse(FeedSource::where('url', 'https://www.panet.co.il/rss/news')->where('active', true)->exists());
    }

    public function test_setup_splits_the_club_groups_by_language()
    {
        Storage::fake('public');
        // As before: דוד and Марина both in "Новости Израиля" (owned by דוד), Фаина in "Кулинария" with a Hebrew source.
        $make = fn (string $slug, string $name, string $locale) => User::forceCreate(['name' => $name, 'username' => $slug, 'email' => $slug.'@authors.anahnu.club', 'password' => 'x', 'locale' => $locale]);
        $david = $make('david', 'דוד', 'he');
        $marina = $make('marina', 'Марина', 'ru');
        $faina = $make('faina', 'Фаина', 'ru');
        $israel = Group::create(['owner_id' => $david->id, 'name' => 'Новости Израиля', 'slug' => 'israel', 'locale' => 'ru']);
        $cooking = Group::create(['owner_id' => $faina->id, 'name' => 'Кулинария', 'slug' => 'cooking', 'locale' => 'ru']);
        $israel->members()->attach([$david->id => ['role' => 'owner', 'approved' => true], $marina->id => ['role' => 'admin', 'approved' => true]]);
        $davidChannel = FeedChannel::create(['user_id' => $david->id, 'group_id' => $israel->id, 'topic' => 'x', 'daily_limit' => 3]);
        FeedChannel::create(['user_id' => $marina->id, 'group_id' => $israel->id, 'topic' => 'x', 'daily_limit' => 2]);
        $fainaChannel = FeedChannel::create(['user_id' => $faina->id, 'group_id' => $cooking->id, 'topic' => 'x', 'daily_limit' => 2]);
        $foody = FeedSource::create(['feed_channel_id' => $fainaChannel->id, 'name' => 'Foody', 'url' => 'https://www.foody.co.il/feed', 'locale' => 'he']);
        $waiting = FeedItem::create(['feed_channel_id' => $fainaChannel->id, 'feed_source_id' => $foody->id, 'title' => 'מתכון', 'url' => 'https://www.foody.co.il/1', 'status' => 'approved']);
        $hebrew = Post::create(['user_id' => $david->id, 'group_id' => $israel->id, 'body' => 'חדשות', 'locale' => 'he']);
        $russian = Post::create(['user_id' => $marina->id, 'group_id' => $israel->id, 'body' => 'Новости', 'locale' => 'ru']);

        $this->artisan('feeds:setup')->assertSuccessful();

        $hebrewGroup = Group::firstWhere('slug', 'israel-he');
        $this->assertSame(['חדשות ישראל', 'he'], [$hebrewGroup->name, $hebrewGroup->locale]);
        $this->assertSame($hebrewGroup->id, $davidChannel->fresh()->group_id);
        // The Hebrew news moved to the Hebrew group; the Russian group is Марина's now, without דוד.
        $this->assertSame($hebrewGroup->id, $hebrew->fresh()->group_id);
        $this->assertSame($israel->id, $russian->fresh()->group_id);
        $this->assertSame($marina->id, $israel->fresh()->owner_id);
        $this->assertFalse($israel->members()->whereKey($david->id)->exists());
        // "Кулинария" writes in English now, as Linda; her Hebrew source is off, with what waited from it.
        $this->assertSame(['Cooking', 'en'], [$cooking->fresh()->name, $cooking->fresh()->locale]);
        $this->assertSame('Linda', $faina->fresh()->name);
        $this->assertFalse($foody->fresh()->active);
        $this->assertSame('skipped', $waiting->fresh()->status);
        // Every club group got its picture.
        $this->assertNotNull($cooking->fresh()->avatar_path);
        Storage::disk('public')->assertExists($hebrewGroup->avatar_path);

        // Run again: nothing moves back.
        $this->artisan('feeds:setup')->assertSuccessful();
        $this->assertSame($hebrewGroup->id, $hebrew->fresh()->group_id);
        $this->assertSame(1, Group::where('slug', 'israel-he')->count());
    }

    public function test_setup_gives_each_author_a_picture_and_a_cover_but_keeps_one_set_by_hand()
    {
        Storage::fake('public');
        $izya = User::forceCreate(['name' => 'Изя', 'username' => 'izya', 'email' => 'izya@authors.anahnu.club', 'password' => 'x', 'locale' => 'ru', 'avatar_path' => 'avatars/by-admin.jpg']);

        $this->artisan('feeds:setup')->assertSuccessful();

        $authors = User::where('email', 'like', '%@authors.anahnu.club')->get();
        $this->assertCount(count(glob(resource_path('club-authors/*.png'))), $authors);
        foreach ($authors as $author) {
            $this->assertNotNull($author->cover_path, $author->name);
            Storage::disk('public')->assertExists($author->cover_path);
            if (! $author->is($izya)) {
                Storage::disk('public')->assertExists($author->avatar_path);
            }
        }
        // The picture an admin set stays; Изя still gets the cover he had none of.
        $this->assertSame('avatars/by-admin.jpg', $izya->fresh()->avatar_path);
        $this->assertSame('covers/club-izya.jpg', $izya->fresh()->cover_path);
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
        $this->assertSame('telegram', PageParsers::kind('https://t.me/kunuzru'));
        $this->assertSame('https://t.me/s/kunuzru', PageParsers::telegramUrl('https://t.me/kunuzru'));
        $this->assertSame('https://t.me/s/kunuzru', PageParsers::telegramUrl('@kunuzru'));
        $this->assertSame('panet', PageParsers::kind('https://panet.com/'));
        $this->assertSame('feed', PageParsers::kind('https://www.panet.co.il/rss/news'));
        $this->assertSame('bokra', PageParsers::kind('https://bokra.net/'));

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

    public function test_three_newest_a_source_each_run_and_the_main_news_goes_first()
    {
        $channel = $this->author();
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Gazeta.uz', 'url' => 'https://gazeta.uz/rss']);
        Http::fake(['gazeta.uz/*' => Http::response($this->rss(collect(range(1, 6))->map(fn ($i) => [
            'title' => "Новость номер $i", 'link' => "https://gazeta.uz/$i", 'date' => now()->subMinutes(10 * $i)->toRssString(),
        ])->all()))]);

        $this->assertSame(3, app(FeedCollector::class)->collect($source));
        $this->assertEqualsCanonicalizing(['https://gazeta.uz/1', 'https://gazeta.uz/2', 'https://gazeta.uz/3'], FeedItem::pluck('url')->all());

        // A fuller item with a picture beats a slightly newer bare one; a curator's own item goes before both.
        FeedItem::where('url', 'https://gazeta.uz/2')->update(['image' => 'https://img/2.jpg', 'summary' => str_repeat('Подробно. ', 15)]);
        $dayStart = now()->startOfDay();
        $this->assertSame('https://gazeta.uz/2', FeedPublisher::choose($channel, $dayStart)->url);
        FeedItem::create(['feed_channel_id' => $channel->id, 'summary' => 'Анекдот', 'status' => 'approved']);
        $this->assertSame('Анекдот', FeedPublisher::choose($channel, $dayStart)->summary);
    }

    public function test_news_not_posted_within_12_hours_leaves_the_queue_and_later_is_deleted()
    {
        $channel = $this->author();
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Gazeta.uz', 'url' => 'https://gazeta.uz/rss']);
        $old = FeedItem::create(['feed_channel_id' => $channel->id, 'feed_source_id' => $source->id, 'title' => 'Вчерашнее', 'status' => 'approved', 'published_at' => now()->subHours(13)]);
        $fresh = FeedItem::create(['feed_channel_id' => $channel->id, 'feed_source_id' => $source->id, 'title' => 'Свежее', 'status' => 'approved', 'published_at' => now()->subHours(2)]);
        $manual = FeedItem::create(['feed_channel_id' => $channel->id, 'summary' => 'Анекдот', 'status' => 'approved', 'published_at' => now()->subDay()]);

        FeedPublisher::expire();
        $this->assertSame('skipped', $old->fresh()->status);
        $this->assertSame('approved', $fresh->fresh()->status);
        $this->assertSame('approved', $manual->fresh()->status);

        Carbon::setTestNow(now()->addDays(3));
        FeedPublisher::expire();
        $this->assertNull($old->fresh());
    }

    public function test_an_author_keeps_only_its_five_best_news_waiting()
    {
        $channel = $this->author();
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Gazeta.uz', 'url' => 'https://gazeta.uz/rss']);
        $collector = app(FeedCollector::class);
        foreach (range(1, 3) as $run) {
            $collector->store($source, collect(range(1, 3))->map(fn ($i) => [
                'title' => "Новость $run-$i", 'url' => "https://gazeta.uz/$run/$i", 'summary' => 'Коротко',
                'image' => $run === 1 && $i === 1 ? 'https://img/1.jpg' : null, 'published_at' => now()->subMinutes(10 * $run + $i),
            ])->all());
        }
        FeedItem::create(['feed_channel_id' => $channel->id, 'summary' => 'Анекдот', 'status' => 'approved']);
        FeedCollector::trim($channel);

        $waiting = FeedItem::where('status', 'approved')->pluck('url', 'id');
        $this->assertCount(6, $waiting, 'five from sources and the hand-added one');
        $this->assertContains('https://gazeta.uz/1/1', $waiting->all(), 'the one with a picture stays');
        $this->assertSame('stale', FeedItem::where('status', 'skipped')->value('reason'));
    }

    public function test_the_lines_a_site_adds_to_every_item_are_left_out()
    {
        $this->assertSame('The death throes of a vanishing ocean.', FeedReader::withoutBoilerplate(
            '<p>The death throes of a vanishing ocean.</p> ScienceAlert stories are written, fact-checked, and edited by humans, never generated by AI. Don\'t miss a story, subscribe here.'
        ));
        $this->assertSame('Новость дня.', FeedReader::withoutBoilerplate('Новость дня. The post Новость appeared first on Site.'));
    }

    public function test_setup_renames_an_author_into_its_own_language_once()
    {
        Storage::fake('public');
        $this->artisan('feeds:setup')->assertSuccessful();
        $farik = User::where('email', 'farik@authors.anahnu.club')->first();
        $farik->forceFill(['name' => 'Фарик', 'locale' => 'ru'])->save();

        $this->artisan('feeds:setup')->assertSuccessful();
        $farik->refresh();
        $this->assertSame('Farik', $farik->name);
        $this->assertSame('uz', $farik->locale);
        $this->assertStringStartsWith('O‘zbekiston', $farik->feedChannel()->first()->topic);

        // A name the admin gave later is the admin's.
        $farik->forceFill(['name' => 'Фархад'])->save();
        $this->artisan('feeds:setup')->assertSuccessful();
        $this->assertSame('Фархад', $farik->fresh()->name);
    }

    public function test_news_authors_post_a_third_less_and_the_light_topics_keep_their_numbers()
    {
        config(['feeds.frequency' => 2 / 3]);
        $news = $this->author(['daily_limit' => 3]);
        $jokes = $this->author(['daily_limit' => 3]);
        $jokes->update(['group_id' => Group::create(['name' => 'Юмор', 'slug' => 'humor'])->id]);
        $rare = $this->author(['daily_limit' => 1]);

        $days = collect(range(1, 60))->map(fn ($d) => Carbon::parse('2026-11-01')->addDays($d)->toDateString());
        $this->assertSame(120, $days->sum(fn ($d) => FeedPublisher::limitToday($news, $d)));
        $this->assertSame(180, $days->sum(fn ($d) => FeedPublisher::limitToday($jokes->fresh(), $d)));
        // One post a day becomes a post on about two days of three.
        $this->assertEqualsWithDelta(40, $days->sum(fn ($d) => FeedPublisher::limitToday($rare, $d)), 10);
    }

    public function test_a_youtube_channel_is_a_source()
    {
        Http::fake([
            'www.youtube.com/@odessa*' => Http::response('<html><link rel="canonical" href="https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv"></html>'),
            'www.youtube.com/feeds/*' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">'
                .'<entry><title>Одесский анекдот про Сару</title><link rel="alternate" href="https://www.youtube.com/watch?v=abc"/>'
                .'<published>'.now()->subHour()->toAtomString().'</published>'
                .'<media:group><media:thumbnail url="https://i.ytimg.com/vi/abc/hqdefault.jpg"/><media:description>Сара, ты где?</media:description></media:group></entry></feed>'),
        ]);

        $this->assertSame('youtube', PageParsers::kind('https://www.youtube.com/@odessa'));
        $items = app(FeedReader::class)->read('https://www.youtube.com/@odessa');

        $this->assertSame('Одесский анекдот про Сару', $items[0]['title']);
        $this->assertSame('https://www.youtube.com/watch?v=abc', $items[0]['url']);
        $this->assertSame('Сара, ты где?', $items[0]['summary']);
        $this->assertSame('https://i.ytimg.com/vi/abc/hqdefault.jpg', $items[0]['image']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'channel_id=UCabcdefghijklmnopqrstuv'));
    }

    public function test_a_joke_is_posted_whole_with_its_lines()
    {
        $channel = $this->author();
        $source = FeedSource::create(['feed_channel_id' => $channel->id, 'name' => 'Анекдоты', 'url' => 'https://www.anekdot.ru/rss/export_j.xml', 'full_text' => true]);
        $joke = '— Сара, где деньги?<br>— Моня, а ты их клал?<br>'.str_repeat('Длинная история про Привоз. ', 20);

        app(FeedCollector::class)->store($source, [app(FeedReader::class)->item('Анекдот', 'https://www.anekdot.ru/id/1/', $joke, '', now()->toRssString())]);

        $summary = FeedItem::first()->summary;
        $this->assertStringStartsWith("— Сара, где деньги?\n— Моня, а ты их клал?\n", $summary);
        $this->assertStringNotContainsString('…', $summary);
    }
}
