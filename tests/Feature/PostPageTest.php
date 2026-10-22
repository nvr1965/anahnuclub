<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shared_post_link_opens_for_guests_with_preview_tags()
    {
        $post = Post::create(['user_id' => User::factory()->create()->id, 'title' => 'Шаббат в парке', 'body' => '<p>Приходите все</p>']);

        $this->get("/posts/{$post->id}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Шаббат в парке">', false)
            ->assertSee('content="Приходите все"', false);
    }

    public function test_a_shared_post_link_shows_its_photo_large_or_else_the_invitation()
    {
        $plain = Post::create(['user_id' => User::factory()->create()->id, 'body' => 'Без фото']);
        $this->get("/posts/{$plain->id}")->assertSee('/images/invite/', false);

        $post = Post::create(['user_id' => User::factory()->create()->id, 'body' => 'С фото']);
        $post->photos()->create(['user_id' => $post->user_id, 'path' => 'photos/invite.jpg']);
        $this->get("/posts/{$post->id}")->assertSee('<meta property="og:image" content="'.asset('storage/photos/invite.jpg').'">', false);
    }

    public function test_a_post_without_a_title_is_named_by_its_text_on_facebook()
    {
        $author = User::factory()->create(['name' => 'Дана']);
        $post = Post::create(['user_id' => $author->id, 'body' => '<p>Сегодня отличная погода</p>']);
        $this->get("/posts/{$post->id}")->assertSee('<meta property="og:title" content="Дана: Сегодня отличная погода">', false);

        // The club's own posts have no author shown: the text alone, never just "anahnu.club".
        config(['app.super_admin_email' => 'boss@example.com']);
        $boss = User::factory()->create(['email' => 'boss@example.com', 'is_admin' => true]);
        $official = Post::create(['user_id' => $boss->id, 'body' => '<p>Новости клуба</p>']);
        $this->get("/posts/{$official->id}")->assertSee('<meta property="og:title" content="Новости клуба">', false);
    }

    public function test_a_post_that_is_only_a_youtube_link_is_named_by_the_clip()
    {
        Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Песни у костра'])]);
        $post = Post::create(['user_id' => User::factory()->create(['name' => 'Дана'])->id, 'body' => 'https://www.youtube.com/embed/gj2LCn7lam8?si=PyLPngodTflJOtbj']);

        $this->get("/posts/{$post->id}")
            ->assertSee('<meta property="og:title" content="Дана: Песни у костра">', false)
            ->assertSee('<meta property="og:image" content="https://i.ytimg.com/vi/gj2LCn7lam8/hqdefault.jpg">', false)
            ->assertDontSee('<meta property="og:description" content="https://', false);
        Http::assertSent(fn ($request) => str_contains($request->url(), urlencode('https://www.youtube.com/watch?v=gj2LCn7lam8')));
    }

    public function test_a_post_that_is_only_a_facebook_link_says_where_it_is_from()
    {
        Http::fake(['*' => Http::response('<html><title></title></html>')]);
        config(['app.super_admin_email' => 'boss@example.com']);
        $boss = User::factory()->create(['email' => 'boss@example.com', 'is_admin' => true]);
        $post = Post::create(['user_id' => $boss->id, 'body' => 'https://www.facebook.com/somepage/posts/pfbid0abc']);

        $this->get("/posts/{$post->id}?lang=en")->assertSee('<meta property="og:title" content="Post from Facebook">', false);
    }

    public function test_a_link_shared_in_hebrew_gets_the_hebrew_card()
    {
        $post = Post::create(['user_id' => User::factory()->create()->id, 'body' => '']);

        $this->get("/posts/{$post->id}?lang=he")->assertSee('images/invite/he.jpg', false);
    }

    public function test_posts_for_friends_only_give_strangers_no_preview_of_their_text()
    {
        $post = Post::create(['user_id' => User::factory()->create()->id, 'body' => 'Только для друзей', 'audience' => 'friends']);

        $this->get("/posts/{$post->id}")->assertNotFound()->assertDontSee('Только для друзей');
    }

    public function test_posts_of_private_groups_stay_private()
    {
        $group = Group::create(['name' => 'Secret', 'slug' => 'secret', 'is_private' => true]);
        $post = Post::create(['group_id' => $group->id, 'body' => 'hidden']);

        $this->get("/posts/{$post->id}")->assertNotFound();
    }

    public function test_authors_can_edit_their_own_posts_only()
    {
        $author = User::factory()->create();
        $this->actingAs($author)->post('/posts', ['body' => "Первая строка\nвторая"]);
        $post = Post::firstOrFail();

        $this->get('/feed')->assertInertia(fn ($page) => $page->where('posts.data.0.editable_text', "Первая строка\nвторая"));
        $this->patch("/posts/{$post->id}", ['body' => 'Исправлено'])->assertRedirect();
        $this->assertSame('Исправлено', $post->fresh()->body);

        $this->actingAs(User::factory()->create())->patch("/posts/{$post->id}", ['body' => 'взлом'])->assertForbidden();
    }
}
