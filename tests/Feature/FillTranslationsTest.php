<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fill_stores_translations_so_the_feed_shows_them_without_waiting()
    {
        $fake = new FakeTranslator;
        $this->app->instance(Translator::class, $fake);
        $reader = User::factory()->create(['locale' => 'en']);
        $post = Post::create(['user_id' => $reader->id, 'body' => 'Привет из Хайфы']);
        $post->forceFill(['created_at' => now()->subYear()])->save();

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation', null));

        $this->artisan('translations:fill')->assertSuccessful();
        $calls = $fake->calls;
        $this->assertGreaterThan(0, $calls);

        $this->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation.pending', false)
            ->where('posts.data.0.translation.fields.body', '[en] Привет из Хайфы'));

        $this->artisan('translations:fill')->assertSuccessful();
        $this->assertSame($calls, $fake->calls, 'a second run only translates what is missing');
    }

    public function test_fill_stops_at_the_character_budget()
    {
        $fake = new FakeTranslator;
        $this->app->instance(Translator::class, $fake);
        $user = User::factory()->create();
        Post::create(['user_id' => $user->id, 'body' => str_repeat('Привет ', 10)]);
        Post::create(['user_id' => $user->id, 'body' => str_repeat('Пока ', 10)]);

        $this->artisan('translations:fill', ['--chars' => 80, '--locale' => ['en']])->assertSuccessful();
        $this->assertSame(1, $fake->calls);
    }
}
