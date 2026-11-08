<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Support\PostTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Tagging friends in a post ("with …" and @name) and #hashtags in any alphabet. */
class PostTagsTest extends TestCase
{
    use RefreshDatabase;

    private function befriend(User $a, User $b): void
    {
        DB::table('friendships')->insert(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted' => true]);
    }

    public function test_hashtags_are_found_in_every_alphabet()
    {
        $this->assertSame(
            ['шаббат', 'שבת', 'سبت', 'haifa2026', 'тель_авив'],
            PostTags::hashtags('#Шаббат шалом! #שבת #سبت, #Haifa2026 и #Тель_Авив. #1 https://x.com/a#b mail#tag &#039; #шаббат'),
        );
        $this->assertTrue(PostTags::isHashtag('#שבת'));
        $this->assertFalse(PostTags::isHashtag('#12'));
        $this->assertFalse(PostTags::isHashtag('два слова'));
    }

    public function test_a_post_with_friends_and_hashtags()
    {
        [$anna, $boris, $vera, $gleb] = User::factory()->count(4)->sequence(
            ['name' => 'Анна'], ['name' => 'Борис Петров'], ['name' => 'Вера'], ['name' => 'Глеб'],
        )->create();
        $this->befriend($anna, $boris);
        $this->befriend($vera, $anna);

        $this->actingAs($anna)->post('/posts', [
            'body' => "Гуляем с @Борис Петров по Хайфе #Хайфа #море\n@Глеб тоже",
            'tagged_ids' => [$vera->id, $gleb->id],
            'audience' => 'only_me',
        ])->assertRedirect();

        $post = Post::firstOrFail();
        // Boris is named in the text and links to his profile; Gleb is not a friend and stays as text.
        $this->assertStringContainsString('<a href="/people/'.$boris->id.'" translate="no">Борис Петров</a>', $post->body);
        $this->assertStringContainsString('<a href="/hashtag/%D1%85%D0%B0%D0%B9%D1%84%D0%B0" translate="no">#Хайфа</a>', $post->body);
        $this->assertStringContainsString('@Глеб', $post->body);
        $this->assertEqualsCanonicalizing(['хайфа', 'море'], DB::table('post_hashtags')->where('post_id', $post->id)->pluck('tag')->all());
        $this->assertEqualsCanonicalizing(
            ["{$vera->id}:with", "{$boris->id}:mention"],
            DB::table('post_user_tags')->get()->map(fn ($t) => "{$t->user_id}:{$t->kind}")->all(),
        );

        // Each one tagged hears of it; Gleb, not a friend, does not.
        $this->assertSame('post_tagged', $vera->notifications()->first()?->data['kind']);
        $this->assertSame('post_mentioned', $boris->notifications()->first()?->data['kind']);
        $this->assertSame(0, $gleb->notifications()->count());

        // "Only me", yet those tagged see it, on their own profile too; others do not.
        $this->assertTrue($post->isVisibleTo($vera));
        $this->assertTrue($post->isVisibleTo($boris));
        $this->assertFalse($post->isVisibleTo($gleb));
        $this->actingAs($vera)->get("/people/{$vera->id}")->assertInertia(fn ($page) => $page
            ->where('posts.data.0.id', $post->id)
            ->where('posts.data.0.with.0.name', 'Вера')
            ->where('posts.data.0.tagged_me', true));

        // The author edits the text: the name comes back as "@Name".
        $this->actingAs($anna)->get("/people/{$anna->id}")->assertInertia(fn ($page) => $page
            ->where('posts.data.0.editable_text', "Гуляем с @Борис Петров по Хайфе #Хайфа #море\n@Глеб тоже"));
    }

    public function test_editing_updates_tags_and_hashtags_and_a_member_can_untag_themself()
    {
        [$anna, $boris, $vera] = User::factory()->count(3)->sequence(['name' => 'Анна'], ['name' => 'Борис'], ['name' => 'Вера'])->create();
        $this->befriend($anna, $boris);
        $this->befriend($anna, $vera);
        $this->actingAs($anna)->post('/posts', ['body' => 'Привет @Борис #старое', 'tagged_ids' => [$vera->id]]);
        $post = Post::firstOrFail();

        $this->actingAs($anna)->patch("/posts/{$post->id}", ['body' => 'Привет всем #новое'])->assertRedirect();
        $this->assertSame(['новое'], DB::table('post_hashtags')->pluck('tag')->all());
        // The mention left with the text; Vera, tagged "with", stays.
        $this->assertSame(["{$vera->id}:with"], DB::table('post_user_tags')->get()->map(fn ($t) => "{$t->user_id}:{$t->kind}")->all());

        $this->actingAs($vera)->delete("/posts/{$post->id}/tag")->assertRedirect();
        $this->assertSame(0, DB::table('post_user_tags')->count());
    }

    public function test_the_hashtag_page_lists_posts_the_reader_may_see()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $owner = User::factory()->create();
        $closed = Group::create(['owner_id' => $owner->id, 'name' => 'Closed', 'slug' => 'closed', 'is_private' => true, 'published' => true]);
        $open = Post::create(['user_id' => $anna->id, 'body' => 'Море #Хайфа', 'audience' => 'public']);
        Post::create(['user_id' => $anna->id, 'body' => 'Только мне #хайфа', 'audience' => 'only_me']);
        Post::create(['user_id' => $owner->id, 'group_id' => $closed->id, 'body' => 'Секрет #хайфа', 'audience' => 'public']);
        Post::create(['user_id' => $anna->id, 'body' => 'Другое #тель_авив', 'audience' => 'public']);

        // Any case leads to one address.
        $this->get('/hashtag/'.rawurlencode('Хайфа'))->assertRedirect('/hashtag/'.rawurlencode('хайфа'));

        $this->get('/hashtag/'.rawurlencode('хайфа'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('hashtags/show')
            ->where('tag', 'хайфа')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $open->id)
            ->where('posts.data.0.body', 'Море <a href="/hashtag/%D1%85%D0%B0%D0%B9%D1%84%D0%B0" translate="no">#Хайфа</a>'));

        // Searching "#хайфа" opens the page; the composer hints the hashtags used.
        $this->actingAs($boris)->get('/search?q='.rawurlencode('#Хайфа'))->assertRedirect('/hashtag/'.rawurlencode('хайфа'));
        $this->actingAs($boris)->getJson('/hashtags/suggest?q='.rawurlencode('тел'))->assertExactJson(['тель_авив']);
        $this->get('/hashtag/12')->assertNotFound();
    }
}
