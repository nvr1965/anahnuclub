<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/feed')->assertRedirect('/login');
    }

    public function test_members_see_posts_in_the_feed()
    {
        $user = User::factory()->create();
        Post::create(['user_id' => $user->id, 'body' => 'Шалом всем']);

        $this->actingAs($user)->get('/feed')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('feed')->has('posts.data', 1));
    }

    public function test_members_can_post_comment_and_like()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['body' => 'Привет <script>x</script> https://example.com'])->assertRedirect();
        $post = Post::firstOrFail();
        $this->assertSame('ru', $post->locale);

        $this->post("/posts/{$post->id}/comments", ['body' => 'Great'])->assertRedirect();
        $this->post("/posts/{$post->id}/like")->assertRedirect();

        $this->assertSame(1, $post->comments()->count());
        $this->assertSame(1, $post->likes()->count());

        $this->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.body', fn ($body) => ! str_contains($body, '<script') && str_contains($body, 'href="https://example.com"')));
    }

    public function test_interface_language_can_be_switched()
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($user)->post('/locale', ['locale' => 'he'])->assertRedirect();
        $this->assertSame('he', $user->fresh()->ui_locale);
        // The default language set in the profile stays.
        $this->assertSame('ru', $user->fresh()->locale);

        $this->get('/feed')->assertInertia(fn ($page) => $page->where('locale', 'he')->where('dir', 'rtl')
            ->where('translations.Feed', 'פיד'));
    }
}
