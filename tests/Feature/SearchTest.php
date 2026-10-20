<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_people_and_groups(): void
    {
        $user = User::factory()->create(['name' => 'Searcher']);
        User::factory()->create(['name' => 'Anna Levi']);
        Group::create(['name' => 'Haifa friends', 'slug' => 'haifa-friends', 'owner_id' => $user->id]);

        $this->actingAs($user)->get('/search?q=levi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('search')->has('results.people', 1)->where('counts.people', 1));

        $this->actingAs($user)->get('/search?q=haifa')
            ->assertInertia(fn ($page) => $page->has('results.groups', 1));
    }

    public function test_search_covers_posts_comments_videos_events_and_pages(): void
    {
        $user = User::factory()->create(['name' => 'Searcher']);
        $byComment = Post::create(['user_id' => $user->id, 'body' => 'Hello']);
        Comment::create(['post_id' => $byComment->id, 'user_id' => $user->id, 'body' => 'See you at the Kinneret']);
        Post::create(['user_id' => $user->id, 'body' => 'Trip to Kinneret']);
        Video::create(['user_id' => $user->id, 'title' => 'Kinneret sunset', 'provider' => 'youtube', 'provider_id' => 'abc', 'url' => 'https://youtu.be/abc']);
        Event::create(['user_id' => $user->id, 'title' => 'Picnic', 'location' => 'Kinneret beach', 'starts_at' => now()->addDay()]);
        $secret = Group::create(['name' => 'Kinneret insiders', 'slug' => 'kin', 'owner_id' => $user->id, 'is_private' => true]);

        $this->actingAs($user)->get('/search?q=kinneret')->assertInertia(fn ($page) => $page
            ->where('counts.posts', 2)
            ->where('counts.videos', 1)
            ->where('counts.events', 1)
            ->where('counts.groups', 0)
        );

        $secret->members()->attach($user->id);
        $this->actingAs($user)->get('/search?q=kinneret&type=groups')->assertInertia(fn ($page) => $page
            ->where('type', 'groups')
            ->has('results.groups', 1)
            ->has('results.posts', 0)
        );

        $this->actingAs($user)->get('/search?q=privacy')->assertInertia(fn ($page) => $page->where('counts.pages', fn ($n) => $n >= 1));
    }
}
