<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** "Share" inside the club, as on Facebook: to one's own feed, to a group, onto a friend's profile. */
class ShareInClubTest extends TestCase
{
    use RefreshDatabase;

    private function befriend(User $a, User $b): void
    {
        DB::table('friendships')->insert(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted' => true]);
    }

    private function group(User $owner, array $members = [], bool $private = false): Group
    {
        $group = Group::create(['owner_id' => $owner->id, 'name' => 'Клуб', 'slug' => 'club-'.uniqid(), 'is_private' => $private, 'published' => true]);
        $group->members()->attach($owner->id, ['role' => 'owner', 'approved' => true]);
        foreach ($members as $m) {
            $group->members()->attach($m->id, ['role' => 'member', 'approved' => true]);
        }

        return $group;
    }

    public function test_a_member_shares_a_post_to_their_own_feed_with_their_words()
    {
        [$anna, $boris, $vera] = User::factory()->count(3)->create();
        $original = Post::create(['user_id' => $anna->id, 'body' => 'Оригинал', 'audience' => 'public']);

        $this->actingAs($boris)->post("/posts/{$original->id}/share", ['target' => 'feed', 'body' => 'Смотрите!', 'audience' => 'public'])->assertRedirect();

        $share = Post::where('user_id', $boris->id)->firstOrFail();
        $this->assertSame($original->id, $share->shared_post_id);
        $this->assertSame('share', $share->type);
        $this->assertNull($share->group_id);
        $this->assertNull($share->wall_user_id);

        $this->actingAs($vera)->get("/people/{$boris->id}")->assertInertia(fn ($page) => $page
            ->where('posts.data.0.body', 'Смотрите!')
            ->where('posts.data.0.shared.post.id', $original->id)
            ->where('posts.data.0.shared.post.body', 'Оригинал')
            ->where('posts.data.0.can_share', true));

        // The author hears of it.
        $this->assertSame('post_shared', $anna->notifications()->first()?->data['kind']);

        // A share of the share carries the original.
        $this->actingAs($vera)->post("/posts/{$share->id}/share", ['target' => 'feed'])->assertRedirect();
        $this->assertSame($original->id, Post::where('user_id', $vera->id)->firstOrFail()->shared_post_id);
    }

    public function test_sharing_to_a_group_needs_membership_and_closed_posts_cannot_be_shared()
    {
        [$anna, $boris, $gleb] = User::factory()->count(3)->create();
        $group = $this->group($anna, [$boris]);
        $original = Post::create(['user_id' => $anna->id, 'body' => 'Новость', 'audience' => 'public']);

        $this->actingAs($gleb)->post("/posts/{$original->id}/share", ['target' => 'group', 'group_id' => $group->id])->assertForbidden();
        $this->actingAs($boris)->post("/posts/{$original->id}/share", ['target' => 'group', 'group_id' => $group->id])->assertRedirect();
        $this->assertSame($group->id, Post::where('user_id', $boris->id)->firstOrFail()->group_id);

        $this->actingAs($boris)->getJson('/share/groups')->assertJsonPath('0.id', $group->id);
        $this->actingAs($gleb)->getJson('/share/groups')->assertExactJson([]);

        // A post in a private group, or one for friends only, stays where it is.
        $secret = $this->group($anna, [$boris], private: true);
        $inside = Post::create(['user_id' => $anna->id, 'group_id' => $secret->id, 'body' => 'Внутри']);
        $this->actingAs($boris)->post("/posts/{$inside->id}/share", ['target' => 'feed'])->assertForbidden();
        $this->befriend($anna, $boris);
        $friendsOnly = Post::create(['user_id' => $anna->id, 'body' => 'Для друзей', 'audience' => 'friends']);
        $this->actingAs($boris)->post("/posts/{$friendsOnly->id}/share", ['target' => 'feed'])->assertForbidden();
    }

    public function test_a_post_on_a_friends_profile_shows_there_and_the_friend_may_delete_it()
    {
        [$anna, $boris, $gleb] = User::factory()->count(3)->create();
        $this->befriend($anna, $boris);
        $original = Post::create(['user_id' => $gleb->id, 'body' => 'Интересно', 'audience' => 'public']);

        // Only onto a friend's profile.
        $this->actingAs($gleb)->post("/posts/{$original->id}/share", ['target' => 'friend', 'user_id' => $anna->id])->assertForbidden();
        $this->actingAs($boris)->post("/posts/{$original->id}/share", ['target' => 'friend', 'user_id' => $anna->id, 'body' => 'Тебе понравится', 'audience' => 'public'])->assertRedirect();

        $share = Post::where('user_id', $boris->id)->firstOrFail();
        $this->assertSame($anna->id, $share->wall_user_id);
        $this->assertSame('wall_post', $anna->notifications()->first()?->data['kind']);

        $this->actingAs($gleb)->get("/people/{$anna->id}")->assertInertia(fn ($page) => $page
            ->where('posts.data.0.id', $share->id)
            ->where('posts.data.0.wall', ['id' => $anna->id, 'name' => $anna->name])
            ->where('posts.data.0.can_delete', false));
        $this->actingAs($anna)->get("/people/{$anna->id}")->assertInertia(fn ($page) => $page->where('posts.data.0.can_delete', true));

        $this->actingAs($anna)->delete("/posts/{$share->id}")->assertRedirect();
        $this->assertNull($share->fresh());
    }

    public function test_a_share_of_a_deleted_post_says_it_is_gone()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $original = Post::create(['user_id' => $anna->id, 'body' => 'Скоро удалю', 'audience' => 'public']);
        $this->actingAs($boris)->post("/posts/{$original->id}/share", ['target' => 'feed', 'audience' => 'public'])->assertRedirect();
        $original->delete();

        $share = Post::where('user_id', $boris->id)->firstOrFail();
        $this->assertNull($share->shared_post_id);
        $this->actingAs($boris)->get("/people/{$boris->id}")->assertInertia(fn ($page) => $page
            ->where('posts.data.0.shared', ['post' => null])
            ->where('posts.data.0.can_share', false));
    }
}
