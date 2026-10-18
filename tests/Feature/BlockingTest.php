<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_a_member_hides_them_both_ways_and_ends_the_friendship()
    {
        $anna = User::factory()->create();
        $boris = User::factory()->create();
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $boris->id, 'accepted' => true]);
        $post = Post::create(['user_id' => $boris->id, 'body' => 'Boris post']);
        Post::create(['user_id' => $anna->id, 'body' => 'Anna post']);
        Comment::create(['post_id' => Post::create(['body' => 'Club news'])->id, 'user_id' => $boris->id, 'body' => 'Boris comment']);

        $this->actingAs($anna)->post("/people/{$boris->id}/block")->assertRedirect();
        $this->assertSame('none', $anna->friendshipWith($boris));

        $this->get('/feed')->assertInertia(fn ($page) => $page
            ->has('posts.data', 2)
            ->where('posts.data', fn ($posts) => collect($posts)->pluck('body')->doesntContain('Boris post'))
            ->where('posts.data', fn ($posts) => collect($posts)->flatMap(fn ($p) => $p['comments'])->isEmpty())
        );
        $this->get("/people/{$boris->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('profile.blocked_by_me', true)
            ->where('posts', null)
        );
        $this->get('/settings/blocked')->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.id', $boris->id));

        // The other side does not find the blocker at all and cannot reach them.
        $this->actingAs($boris)->get("/people/{$anna->id}")->assertNotFound();
        $this->get('/feed')->assertInertia(fn ($page) => $page->where('posts.data', fn ($posts) => collect($posts)->pluck('body')->doesntContain('Anna post')));
        $this->post("/people/{$anna->id}/friend")->assertStatus(422);
        $this->post("/people/{$anna->id}/message")->assertNotFound();
        $conversation = Conversation::between($anna, $boris);
        $this->post("/messages/{$conversation->id}", ['body' => 'Hi'])->assertForbidden();

        $this->actingAs($anna)->post("/posts/{$post->id}/comments", ['body' => 'Hey'])->assertForbidden();

        $this->delete("/people/{$boris->id}/block")->assertRedirect();
        $this->get('/feed')->assertInertia(fn ($page) => $page->has('posts.data', 3));
    }

    public function test_blocking_a_group_leaves_it_and_hides_its_posts()
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Noisy', 'slug' => 'noisy']);
        $group->members()->attach($user->id, ['approved' => true]);
        Post::create(['user_id' => User::factory()->create()->id, 'group_id' => $group->id, 'body' => 'Group post']);

        $this->actingAs($user)->post('/groups/noisy/block')->assertRedirect();
        $this->assertFalse($group->members()->whereKey($user->id)->exists());
        $this->get('/feed')->assertInertia(fn ($page) => $page->has('posts.data', 0));
        $this->get('/groups/noisy')->assertInertia(fn ($page) => $page->where('group.blocked', true)->has('posts.data', 0));
        $this->post('/groups/noisy/join')->assertStatus(422);

        $this->delete('/groups/noisy/block')->assertRedirect();
        $this->get('/feed')->assertInertia(fn ($page) => $page->has('posts.data', 1));
    }
}
