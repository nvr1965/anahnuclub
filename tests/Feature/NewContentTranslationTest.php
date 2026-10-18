<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    private FakeTranslator $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeTranslator;
        $this->app->instance(Translator::class, $this->fake);
    }

    public function test_a_new_post_and_comment_are_translated_right_after_publishing()
    {
        $author = User::factory()->create(['locale' => 'ru']);
        $reader = User::factory()->create(['locale' => 'he']);

        $this->actingAs($author)->post('/posts', ['body' => 'Всем привет из Хайфы'])->assertRedirect();
        $post = Post::firstOrFail();
        $this->post("/posts/{$post->id}/comments", ['body' => 'Отличная новость'])->assertRedirect();

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation.fields.body', fn ($body) => str_contains($body, '[he]'))
            ->where('posts.data.0.comments.0.translation.fields.body', fn ($body) => str_contains($body, '[he] '))
        );
    }

    public function test_the_page_only_reads_stored_translations_and_leaves_old_posts_alone()
    {
        $reader = User::factory()->create(['locale' => 'en']);
        $fresh = Post::create(['user_id' => $reader->id, 'body' => 'Свежий пост']);
        $old = Post::create(['user_id' => $reader->id, 'body' => 'Старый пост']);
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation.pending', true)
            ->where('posts.data.1.translation', null));

        $this->postJson('/translations', ['items' => [['type' => 'post', 'id' => $fresh->id], ['type' => 'post', 'id' => $old->id]]])
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.fields', []);
        $this->assertSame(0, $this->fake->calls, 'the page never waits for the provider');
    }
}
