<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_author_edits_a_comment_and_the_first_words_stay_for_the_team()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $post = Post::create(['user_id' => $anna->id, 'body' => 'Пост']);
        $comment = $post->comments()->create(['user_id' => $boris->id, 'body' => 'Первый текст', 'locale' => 'ru']);

        $this->actingAs($boris)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.comments.0.editable_text', 'Первый текст')
            ->where('posts.data.0.comments.0.edited', false));

        $this->actingAs($boris)->patch("/comments/{$comment->id}", ['body' => "Новый <текст>\nвторая строка"])->assertRedirect();
        $comment->refresh();
        $this->assertSame('Новый &lt;текст&gt;<br>'."\n".'вторая строка', $comment->body);
        $this->assertSame('Первый текст', $comment->original_body);
        $this->assertNotNull($comment->edited_at);

        // A second edit keeps the words first written.
        $this->actingAs($boris)->patch("/comments/{$comment->id}", ['body' => 'Третий'])->assertRedirect();
        $this->assertSame('Первый текст', $comment->refresh()->original_body);

        // Others see "edited" and cannot edit it.
        $this->actingAs($anna)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.comments.0.edited', true)
            ->where('posts.data.0.comments.0.editable_text', null));
        $this->actingAs($anna)->patch("/comments/{$comment->id}", ['body' => 'Чужой'])->assertForbidden();
        $this->assertSame('Третий', $comment->refresh()->body);
    }

    public function test_the_text_for_editing_turns_lines_back_and_unescapes()
    {
        $comment = new Comment(['body' => 'a &lt;b&gt;<br>'."\n".'c &amp; d']);
        $this->assertSame("a <b>\nc & d", $comment->editableText());
    }

    public function test_comments_from_the_old_site_are_not_edited()
    {
        $anna = User::factory()->create();
        $post = Post::create(['user_id' => $anna->id, 'body' => 'Пост']);
        $comment = $post->comments()->create(['user_id' => $anna->id, 'body' => 'Старый', 'legacy_source' => 'joomla', 'legacy_id' => 7]);

        $this->actingAs($anna)->patch("/comments/{$comment->id}", ['body' => 'Новый'])->assertForbidden();
    }

    public function test_who_may_delete_a_comment()
    {
        [$anna, $boris, $vera] = User::factory()->count(3)->create();
        $post = Post::create(['user_id' => $anna->id, 'body' => 'Пост']);
        $byBoris = $post->comments()->create(['user_id' => $boris->id, 'body' => 'Борис']);
        $byVera = $post->comments()->create(['user_id' => $vera->id, 'body' => 'Вера']);
        $reply = $post->comments()->create(['user_id' => $anna->id, 'body' => 'Ответ', 'parent_id' => $byVera->id]);

        // Someone else's comment on someone else's post: no.
        $this->actingAs($vera)->delete("/comments/{$byBoris->id}")->assertForbidden();
        // Its author: yes.
        $this->actingAs($boris)->delete("/comments/{$byBoris->id}")->assertRedirect();
        $this->assertModelMissing($byBoris);
        // The post's author, with the replies under it.
        $this->actingAs($anna)->delete("/comments/{$byVera->id}")->assertRedirect();
        $this->assertModelMissing($byVera);
        $this->assertModelMissing($reply);
    }

    public function test_a_team_member_deletes_any_comment()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['user_id' => $anna->id, 'body' => 'Пост']);
        $comment = $post->comments()->create(['user_id' => $boris->id, 'body' => 'x']);

        $this->actingAs($admin)->delete("/comments/{$comment->id}")->assertRedirect();
        $this->assertModelMissing($comment);
    }
}
