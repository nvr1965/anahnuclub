<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_members_create_and_run_their_own_group()
    {
        [$me, $guest] = User::factory()->count(2)->create();

        $this->actingAs($me)->post('/groups', ['name' => 'Йога в Хайфе', 'description' => "Каждую субботу\nна пляже", 'is_private' => true])->assertRedirect();
        $group = Group::latest('id')->first();
        $this->assertSame($me->id, $group->owner_id);
        $this->assertSame($group->id.'-yoga-v-hayfe', $group->slug);
        $this->assertSame('owner', $group->roleOf($me));
        $this->get("/groups/{$group->slug}")->assertInertia(fn ($page) => $page->where('group.description_text', "Каждую субботу\nна пляже"));

        // A closed group: the request waits for the owner.
        $this->actingAs($guest)->post("/groups/{$group->slug}/join");
        $this->assertNull($group->roleOf($guest));
        $this->put("/groups/{$group->slug}", ['name' => 'Взлом'])->assertForbidden();
        $this->actingAs($me)->get("/groups/{$group->slug}")->assertInertia(fn ($page) => $page->where('team.0.role', 'pending'));
        $this->post("/groups/{$group->slug}/members/{$guest->id}/approve")->assertRedirect();
        $this->assertSame('member', $group->roleOf($guest));

        $this->put("/groups/{$group->slug}", ['name' => 'Йога', 'is_private' => false])->assertRedirect();
        $this->assertSame('Йога', $group->fresh()->name);
        $this->actingAs($guest)->delete("/groups/{$group->slug}")->assertForbidden();
        $this->actingAs($me)->delete("/groups/{$group->slug}")->assertRedirect('/groups');
        $this->assertNull(Group::find($group->id));
    }

    public function test_members_invite_club_members_into_the_group()
    {
        [$owner, $friend, $other, $member] = User::factory()->count(4)->create();
        $group = $this->group($owner, $member);
        $group->update(['is_private' => true]);
        DB::table('friendships')->insert(['user_id' => $owner->id, 'friend_id' => $friend->id, 'accepted' => true]);

        // Friends first; members of the group are not offered.
        $list = $this->actingAs($owner)->getJson('/groups/haifa/invite')->assertOk()->json();
        $this->assertSame($friend->id, $list[0]['id']);
        $this->assertTrue($list[0]['friend']);
        $this->assertNotContains($member->id, array_column($list, 'id'));
        $this->getJson('/groups/haifa/invite?q='.urlencode($other->name))->assertJsonPath('0.id', $other->id);

        $this->post('/groups/haifa/invite', ['user_ids' => [$friend->id, $other->id, $member->id]])->assertRedirect();
        $this->get('/groups/haifa')->assertInertia(fn ($page) => $page->has('team', 4)->where('team.3.role', 'invited')->where('group.members_count', 2));
        $this->assertSame(2, $group->invitations()->count());
        $this->assertSame('group_invite', $friend->notifications()->first()->data['kind']);
        $this->actingAs(User::factory()->create())->post('/groups/haifa/invite', ['user_ids' => [$other->id]])->assertForbidden();

        // Invited by the owner, the friend walks straight into the closed group.
        $invitation = $group->invitations()->where('user_id', $friend->id)->first();
        $this->actingAs($friend)->get('/groups/haifa')->assertOk()->assertInertia(fn ($page) => $page->where('group.invitation.id', $invitation->id));
        $this->post("/groups/haifa/invitations/{$invitation->id}/accept")->assertRedirect();
        $this->assertSame('member', $group->roleOf($friend));

        $invitation = $group->invitations()->where('user_id', $other->id)->first();
        $this->actingAs($other)->delete("/groups/haifa/invitations/{$invitation->id}")->assertRedirect();
        $this->assertSame(0, $group->invitations()->count());
        $this->assertNull($group->roleOf($other));
    }
}
