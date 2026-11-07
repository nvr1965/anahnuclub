<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PinnedPostsTest extends TestCase
{
    use RefreshDatabase;

    private function write(User $author, string $body, string $when, ?Group $group = null): Post
    {
        return Post::create(['user_id' => $author->id, 'group_id' => $group?->id, 'body' => $body, 'created_at' => Carbon::parse($when), 'updated_at' => Carbon::parse($when)]);
    }

    public function test_the_main_administrator_pins_a_post_on_top_of_everyones_feed()
    {
        $boss = User::factory()->create(['is_admin' => true]);
        config(['app.super_admin_email' => $boss->email]);
        $admin = User::factory()->create(['is_admin' => true]);
        $anna = User::factory()->create();
        $old = $this->write($anna, 'Старый', '-3 days');
        $this->write($anna, 'Новый', '-1 hour');

        // Only the main administrator may.
        $this->actingAs($admin)->post("/posts/{$old->id}/pin/feed")->assertForbidden();
        $this->actingAs($anna)->post("/posts/{$old->id}/pin/feed")->assertForbidden();
        $this->actingAs($anna)->get('/feed')->assertInertia(fn ($page) => $page->where('posts.data.0.body', 'Новый')->where('posts.data.0.pins', ['profile' => false]));

        $this->actingAs($boss)->post("/posts/{$old->id}/pin/feed")->assertRedirect();
        $this->assertNotNull($old->fresh()->pinned_at);

        $this->actingAs($anna)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.body', 'Старый')->where('posts.data.0.pinned', true)
            ->where('posts.data.1.body', 'Новый')->where('posts.data.1.pinned', false));
        // Guests see it on top of the front page too.
        auth()->logout();
        $this->get('/')->assertInertia(fn ($page) => $page->where('posts.data.0.body', 'Старый'));

        // The same button takes the pin off.
        $this->actingAs($boss)->post("/posts/{$old->id}/pin/feed")->assertRedirect();
        $this->assertNull($old->fresh()->pinned_at);
    }

    public function test_members_pin_their_own_posts_on_their_profile()
    {
        [$anna, $dana] = User::factory()->count(2)->create();
        $first = $this->write($anna, 'Первый', '-2 days');
        $this->write($anna, 'Второй', '-1 day');

        $this->actingAs($dana)->post("/posts/{$first->id}/pin/profile")->assertForbidden();
        $this->actingAs($anna)->post("/posts/{$first->id}/pin/profile")->assertRedirect();

        $this->actingAs($dana)->get("/people/{$anna->id}")->assertInertia(fn ($page) => $page
            ->where('posts.data.0.body', 'Первый')->where('posts.data.0.pinned', true)->where('posts.data.0.pins', null));
        // A profile pin does not move the post in the feed.
        $this->get('/feed')->assertInertia(fn ($page) => $page->where('posts.data.0.body', 'Второй')->where('posts.data.1.pinned', false));
        // Pinning is not an edit: the post keeps its date.
        $this->assertTrue($first->fresh()->updated_at->lt(now()->subDay()));
    }

    public function test_the_owner_and_admins_of_a_group_pin_posts_in_it()
    {
        [$owner, $admin, $member] = User::factory()->count(3)->create();
        $group = Group::create(['name' => 'Хайфа', 'slug' => 'haifa', 'owner_id' => $owner->id]);
        $group->members()->attach($owner->id, ['role' => 'owner', 'approved' => true]);
        $group->members()->attach($admin->id, ['role' => 'admin', 'approved' => true]);
        $group->members()->attach($member->id, ['approved' => true]);
        $old = $this->write($member, 'Правила группы', '-5 days', $group);
        $this->write($member, 'Свежее', '-1 hour', $group);

        $this->actingAs($member)->post("/posts/{$old->id}/pin/group")->assertForbidden();
        $this->actingAs($admin)->post("/posts/{$old->id}/pin/group")->assertRedirect();

        $this->actingAs($member)->get('/groups/haifa')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.body', 'Правила группы')->where('posts.data.0.pinned', true));
        $this->actingAs($owner)->get('/groups/haifa')->assertInertia(fn ($page) => $page->where('posts.data.0.pins.group', true));
        $this->actingAs($owner)->post("/posts/{$old->id}/pin/group")->assertRedirect();
        $this->assertNull($old->fresh()->group_pinned_at);

        // A post outside groups has nothing to pin it to there.
        $own = $this->write($owner, 'Без группы', 'now');
        $this->actingAs($owner)->post("/posts/{$own->id}/pin/group")->assertForbidden();
    }
}
