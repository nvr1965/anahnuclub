<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutopostTest extends TestCase
{
    use RefreshDatabase;

    private function send(array $data, string $token = 'secret')
    {
        return $this->withToken($token)->postJson('/api/autopost', $data);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.autopost.token' => 'secret', 'services.autopost.user' => 'news@nofinfo.com']);
    }

    public function test_a_news_item_becomes_a_public_post_with_its_link()
    {
        $bot = User::factory()->create(['email' => 'news@nofinfo.com']);

        $this->send(['text' => 'Ракета упала в Ноф-ха-Галиль', 'url' => 'https://nofinfo.com/?id=5&lang=ru'])
            ->assertCreated()->assertJsonPath('id', Post::first()->id);

        $post = Post::first();
        $this->assertSame($bot->id, $post->user_id);
        $this->assertSame('public', $post->audience);
        $this->assertSame('https://nofinfo.com/?id=5&lang=ru', $post->link_url);
        $this->assertStringContainsString('Ракета упала', $post->body);
    }

    public function test_the_card_is_for_the_link_sent_even_when_the_text_has_another()
    {
        User::factory()->create(['email' => 'news@nofinfo.com']);
        $this->send(['text' => "Новость\n\nПодробнее: https://www.jpost.com/israel-news/1", 'url' => 'https://nofinfo.com/?id=9'])->assertCreated();

        $this->assertSame('https://nofinfo.com/?id=9', Post::first()->link_url);
        $this->send(['text' => 'Новость', 'url' => 'https://nofinfo.com/?id=9'])->assertJsonPath('duplicate', true);
    }

    public function test_the_same_link_twice_stays_one_post()
    {
        User::factory()->create(['email' => 'news@nofinfo.com']);
        $this->send(['text' => 'Новость', 'url' => 'https://nofinfo.com/?id=7'])->assertCreated();
        $this->send(['text' => 'Новость', 'url' => 'https://nofinfo.com/?id=7'])->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, Post::count());
    }

    public function test_posts_go_to_the_group_when_one_is_set()
    {
        User::factory()->create(['email' => 'news@nofinfo.com']);
        $group = Group::create(['name' => 'Новости', 'slug' => 'news']);
        config(['services.autopost.group' => $group->id]);

        $this->send(['text' => 'Новость', 'url' => 'https://nofinfo.com/?id=8'])->assertCreated();
        $this->assertSame($group->id, Post::first()->group_id);
    }

    public function test_a_wrong_or_missing_key_is_refused()
    {
        User::factory()->create(['email' => 'news@nofinfo.com']);
        $this->send(['text' => 'x', 'url' => 'https://nofinfo.com/'], 'wrong')->assertForbidden();
        config(['services.autopost.token' => '']);
        $this->send(['text' => 'x', 'url' => 'https://nofinfo.com/'], '')->assertForbidden();
        $this->assertSame(0, Post::count());
    }

    public function test_only_web_links_are_taken()
    {
        User::factory()->create(['email' => 'news@nofinfo.com']);
        $this->send(['text' => 'x', 'url' => 'javascript:alert(1)'])->assertUnprocessable();
    }
}
