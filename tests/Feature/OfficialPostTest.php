<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_main_administrators_posts_speak_for_the_club_with_comments_closed()
    {
        config(['app.super_admin_email' => 'boss@example.com']);
        $boss = User::factory()->create(['email' => 'boss@example.com', 'is_admin' => true]);
        $anna = User::factory()->create();
        $post = Post::create(['user_id' => $boss->id, 'body' => 'Добро пожаловать']);
        $post->comments()->create(['user_id' => $anna->id, 'body' => 'Старый комментарий']);

        // Everyone sees it, without the author or the comments.
        $this->actingAs($anna)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.id', $post->id)
            ->where('posts.data.0.official', true)
            ->where('posts.data.0.author', null)
            ->where('posts.data.0.comments', []));
        $this->actingAs($anna)->post("/posts/{$post->id}/comments", ['body' => 'Можно?'])->assertForbidden();
        $this->assertSame(1, $post->comments()->count());

        // The administrator still sees themself as its author, to edit it.
        $this->actingAs($boss)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.author.id', $boss->id));
    }

    public function test_other_members_posts_keep_their_author_and_comments()
    {
        config(['app.super_admin_email' => 'boss@example.com']);
        User::factory()->create(['email' => 'boss@example.com', 'is_admin' => true]);
        [$anna, $boris] = User::factory()->count(2)->create();
        $post = Post::create(['user_id' => $anna->id, 'body' => 'Привет']);

        $this->actingAs($boris)->post("/posts/{$post->id}/comments", ['body' => 'Привет!'])->assertRedirect();
        $this->actingAs($boris)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.official', false)
            ->where('posts.data.0.author.id', $anna->id)
            ->has('posts.data.0.comments', 1));
    }
}
