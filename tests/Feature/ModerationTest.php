<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_find_and_remove_unwanted_posts_in_any_language()
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $spammer = User::factory()->create(['name' => 'Spam']);
        $casino = Post::create(['user_id' => $spammer->id, 'body' => 'Лучшее КАЗИНО онлайн, бонус за регистрацию!']);
        $pills = Post::create(['user_id' => $spammer->id, 'body' => 'Buy Viagra without prescription']);
        $hebrew = Post::create(['user_id' => $spammer->id, 'body' => 'שלום']);
        // Caught through its translation.
        DB::table('content_translations')->insert(['translatable_type' => $hebrew->getMorphClass(), 'translatable_id' => $hebrew->id, 'field' => 'body', 'locale' => 'ru', 'text' => 'Проститутки рядом', 'provider' => 'test', 'source_hash' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $fine = Post::create(['user_id' => $spammer->id, 'body' => 'Поездка в Essex была прекрасной']);
        $comment = Comment::create(['post_id' => $fine->id, 'user_id' => $spammer->id, 'body' => 'смотри порно тут']);

        $this->actingAs(User::factory()->create())->get('/admin/moderation')->assertForbidden();
        $this->actingAs($admin)->get('/admin/moderation')->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/moderation')
            ->where('total', 4)
            ->where('counts.casino', 1)
            ->where('counts.drugs', 1)
            ->where('counts.prostitution', 1)
            ->where('counts.porn', 1)
            ->where('authors.0.count', 4));
        $this->get('/admin/moderation?category=casino')->assertInertia(fn ($page) => $page->where('total', 1)->where('items.0.id', $casino->id));

        $this->post('/admin/moderation/keep', ['items' => [['type' => 'post', 'id' => $pills->id]]])->assertRedirect();
        $this->delete('/admin/moderation', ['items' => [['type' => 'post', 'id' => $casino->id], ['type' => 'comment', 'id' => $comment->id]]])->assertRedirect();
        $this->assertNull(Post::find($casino->id));
        $this->assertNull(Comment::find($comment->id));
        $this->get('/admin/moderation')->assertInertia(fn ($page) => $page->where('total', 1));

        // Own words.
        $this->put('/admin/moderation/words', ['words' => ['casino' => 'лохотрон']])->assertRedirect();
        Post::create(['user_id' => $spammer->id, 'body' => 'Настоящий Лохотрон']);
        $this->get('/admin/moderation?category=casino')->assertInertia(fn ($page) => $page->where('total', 1));
    }

    public function test_admins_approve_or_delete_everything_found_at_once()
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $casino = Post::create(['user_id' => $admin->id, 'body' => 'Лучшее казино']);
        $porn = Post::create(['user_id' => $admin->id, 'body' => 'порно тут']);
        $more = Post::create(['user_id' => $admin->id, 'body' => 'ставки на спорт и казино']);

        $this->actingAs($admin)->delete('/admin/moderation', ['all' => true, 'category' => 'casino'])->assertRedirect();
        $this->assertNull(Post::find($casino->id));
        $this->assertNull(Post::find($more->id));
        $this->assertNotNull(Post::find($porn->id));

        $this->post('/admin/moderation/keep', ['all' => true])->assertRedirect();
        $this->get('/admin/moderation')->assertInertia(fn ($page) => $page->where('total', 0));
        $this->assertNotNull(Post::find($porn->id));
    }
}
