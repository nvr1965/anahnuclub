<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentRepliesTest extends TestCase
{
    use RefreshDatabase;

    public function test_replies_stay_one_level_deep_and_notify_the_one_answered()
    {
        [$anna, $boris, $vera] = User::factory()->count(3)->create();
        $post = Post::create(['user_id' => $anna->id, 'body' => 'Пост']);

        $this->actingAs($boris)->post("/posts/{$post->id}/comments", ['body' => 'Первый'])->assertRedirect();
        $first = Comment::latest('id')->first();

        $this->actingAs($vera)->post("/posts/{$post->id}/comments", ['body' => 'Ответ', 'parent_id' => $first->id])->assertRedirect();
        $answer = Comment::latest('id')->first();
        $this->assertSame($first->id, $answer->parent_id);
        $this->assertSame(1, $boris->notifications()->where('data->kind', 'comment_replied')->count());

        // A reply to a reply joins the same thread.
        $this->actingAs($boris)->post("/posts/{$post->id}/comments", ['body' => 'Ещё', 'parent_id' => $answer->id]);
        $this->assertSame($first->id, Comment::latest('id')->first()->parent_id);
        $this->assertSame(1, $vera->notifications()->where('data->kind', 'comment_replied')->count());

        $this->actingAs($anna)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.comments.1.parent_id', $first->id));
    }

    public function test_a_reply_must_answer_a_comment_on_the_same_post()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $one = Post::create(['user_id' => $anna->id, 'body' => 'Один']);
        $two = Post::create(['user_id' => $anna->id, 'body' => 'Два']);
        $comment = $one->comments()->create(['user_id' => $anna->id, 'body' => 'x']);

        $this->actingAs($boris)->post("/posts/{$two->id}/comments", ['body' => 'Не туда', 'parent_id' => $comment->id])->assertNotFound();
    }
}
