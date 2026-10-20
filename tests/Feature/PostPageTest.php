<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
