<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupRolesTest extends TestCase
{
    use RefreshDatabase;

    private function group(User $owner, User ...$members): Group
    {
        $group = Group::create(['name' => 'Хайфа', 'slug' => 'haifa', 'owner_id' => $owner->id]);
        $group->members()->attach($owner->id, ['role' => 'owner']);
        foreach ($members as $m) {
            $group->members()->attach($m->id);
        }

        return $group;
    }

    public function test_a_role_is_given_only_after_the_member_accepts()
    {
        [$owner, $anna, $dana] = User::factory()->count(3)->create();
        $group = $this->group($owner, $anna, $dana);

        $this->actingAs($owner)->post('/groups/haifa/offers', ['user_id' => $anna->id, 'role' => 'admin'])->assertRedirect();
        $this->assertSame('member', $group->roleOf($anna));
        $this->assertSame('group_offer_admin', $anna->notifications()->first()->data['kind']);
        $this->get('/groups/haifa')->assertInertia(fn ($page) => $page->has('team', 3)->where('team.0.role', 'owner'));

        $offer = $group->roleOffers()->first();
        $this->actingAs($anna)->get('/groups/haifa')->assertInertia(fn ($page) => $page->where('group.offer.role', 'admin')->where('team', null));
        $this->post("/groups/haifa/offers/{$offer->id}/accept")->assertRedirect();
        $this->assertSame('admin', $group->roleOf($anna));
        $this->assertSame('group_offer_accepted', $owner->notifications()->first()->data['kind']);

        // An admin may add moderators, but not admins, and cannot hand the group over.
        $this->post('/groups/haifa/offers', ['user_id' => $dana->id, 'role' => 'admin'])->assertForbidden();
        $this->post('/groups/haifa/offers', ['user_id' => $dana->id, 'role' => 'owner'])->assertForbidden();
        $this->post('/groups/haifa/offers', ['user_id' => $dana->id, 'role' => 'moderator'])->assertRedirect();
        $offer = $group->roleOffers()->first();
        $this->actingAs($dana)->delete("/groups/haifa/offers/{$offer->id}")->assertRedirect();
        $this->assertSame('member', $group->roleOf($dana));
        $this->assertSame(0, $group->roleOffers()->count());

        // The owner takes a role back.
        $this->actingAs($owner)->delete("/groups/haifa/roles/{$anna->id}")->assertRedirect();
        $this->assertSame('member', $group->roleOf($anna));
    }

    public function test_the_owner_hands_the_group_over_once_the_new_owner_agrees()
    {
        [$owner, $anna] = User::factory()->count(2)->create();
        $group = $this->group($owner, $anna);
        $outsider = User::factory()->create();

        $this->actingAs($owner)->post('/groups/haifa/offers', ['user_id' => $outsider->id, 'role' => 'owner'])->assertStatus(422);
        $this->post('/groups/haifa/offers', ['user_id' => $anna->id, 'role' => 'owner'])->assertRedirect();
        $this->assertSame($owner->id, $group->fresh()->owner_id);

        $offer = $group->roleOffers()->first();
        $this->actingAs($outsider)->post("/groups/haifa/offers/{$offer->id}/accept")->assertNotFound();
        $this->actingAs($anna)->post("/groups/haifa/offers/{$offer->id}/accept")->assertRedirect();

        $group->refresh();
        $this->assertSame($anna->id, $group->owner_id);
        $this->assertSame('admin', $group->roleOf($owner));
    }

    public function test_moderators_remove_posts_in_their_group()
    {
        [$owner, $mod, $author] = User::factory()->count(3)->create();
        $group = $this->group($owner, $mod, $author);
        $group->members()->updateExistingPivot($mod->id, ['role' => 'moderator']);
        $post = Post::create(['user_id' => $author->id, 'group_id' => $group->id, 'body' => 'Спам']);
        $elsewhere = Post::create(['user_id' => $author->id, 'body' => 'Моё']);

        $this->actingAs($mod)->get('/groups/haifa')->assertInertia(fn ($page) => $page->where('posts.data.0.can_delete', true));
        $this->delete("/posts/{$elsewhere->id}")->assertForbidden();
        $this->delete("/posts/{$post->id}")->assertRedirect();
        $this->assertNull(Post::find($post->id));
    }
}
