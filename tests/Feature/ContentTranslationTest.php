<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    private FakeTranslator $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeTranslator;
        $this->app->instance(Translator::class, $this->fake);
        config(['services.translation.on_demand' => true]);
    }

    public function test_posts_are_translated_into_the_readers_language_and_cached()
    {
        $reader = User::factory()->create(['locale' => 'en']);
        $post = Post::create(['user_id' => $reader->id, 'title' => 'Новости', 'body' => 'Привет из Хайфы']);

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation.pending', true));

        $this->postJson('/translations', ['items' => [['type' => 'post', 'id' => $post->id]]])
            ->assertOk()
            ->assertJsonPath('results.0.fields.title', '[en] Новости')
            ->assertJsonPath('results.0.fields.body', '[en] Привет из Хайфы');

        $this->postJson('/translations', ['items' => [['type' => 'post', 'id' => $post->id]]])->assertOk();
        $this->assertSame(2, $this->fake->calls, 'second request must come from the cache');

        $this->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation.pending', false)
            ->where('posts.data.0.translation.fields.body', '[en] Привет из Хайфы'));
    }

    public function test_text_already_in_the_readers_language_is_not_translated()
    {
        $reader = User::factory()->create(['locale' => 'ru']);
        Post::create(['user_id' => $reader->id, 'body' => 'Привет из Хайфы']);

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation', null));
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_edited_text_is_translated_again()
    {
        $reader = User::factory()->create(['locale' => 'he']);
        $post = Post::create(['user_id' => $reader->id, 'body' => 'Первая версия']);

        $this->actingAs($reader)->postJson('/translations', ['items' => [['type' => 'post', 'id' => $post->id]]]);
        $post->update(['body' => 'Вторая версия']);
        $this->postJson('/translations', ['items' => [['type' => 'post', 'id' => $post->id]]])
            ->assertJsonPath('results.0.fields.body', '[he] Вторая версия');
    }
}
