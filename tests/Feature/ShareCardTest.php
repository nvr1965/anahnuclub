<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** What Facebook, WhatsApp and Telegram see when a link to a members' page is shared. */
class ShareCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shared_video_shows_its_own_card_to_guests_and_robots()
    {
        $video = Video::create(['user_id' => User::factory()->create()->id, 'title' => 'Концерт в Хайфе', 'description' => 'Лучшие песни', 'provider' => 'youtube', 'provider_id' => 'abc', 'url' => 'https://youtu.be/abc']);

        $this->get("/videos/{$video->id}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Концерт в Хайфе">', false)
            ->assertSee('content="Лучшие песни"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_after_signing_in_the_guest_lands_on_the_shared_page()
    {
        $event = Event::create(['user_id' => User::factory()->create()->id, 'title' => 'Шаббат в парке', 'starts_at' => now()->addWeek()]);
        $this->get("/events/{$event->id}")->assertOk()->assertSee('<meta property="og:title" content="Шаббат в парке">', false);

        $member = User::factory()->create();
        $this->post('/login', ['email' => $member->email, 'password' => 'password'])->assertRedirect("/events/{$event->id}");
    }

    public function test_a_public_group_has_its_card_and_a_private_one_sends_to_sign_in()
    {
        $open = Group::create(['name' => 'Хайфа', 'slug' => 'haifa', 'description' => 'Все из Хайфы', 'published' => true]);
        $this->get('/groups/haifa')->assertOk()->assertSee('<meta property="og:title" content="Хайфа · anahnu.club">', false);

        Group::create(['name' => 'Secret', 'slug' => 'secret', 'is_private' => true, 'published' => true]);
        $this->get('/groups/secret')->assertRedirect('/login');
    }

    public function test_a_clip_of_a_friends_only_post_gives_strangers_nothing()
    {
        $author = User::factory()->create();
        $video = Video::create(['user_id' => $author->id, 'title' => 'Семейное видео', 'provider' => 'youtube', 'provider_id' => 'x', 'url' => 'https://youtu.be/x']);
        Post::create(['user_id' => $author->id, 'video_id' => $video->id, 'type' => 'video', 'body' => '', 'audience' => 'friends']);

        $this->get("/videos/{$video->id}")->assertRedirect('/login');
    }

    public function test_members_still_open_the_pages_as_before()
    {
        $member = User::factory()->create();
        $video = Video::create(['user_id' => $member->id, 'title' => 'Моё', 'provider' => 'youtube', 'provider_id' => 'y', 'url' => 'https://youtu.be/y']);

        $this->actingAs($member)->get("/videos/{$video->id}")->assertOk()->assertInertia(fn ($page) => $page->component('videos/show'));
    }
}
