<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Support\Badges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgesTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user): Post
    {
        return Post::create(['user_id' => $user->id, 'type' => 'status', 'body' => 'Пост', 'audience' => 'public']);
    }

    public function test_a_member_rises_through_the_levels_as_they_post_and_get_reactions()
    {
        Badges::saveThresholds([
            'club-member' => ['days' => 0, 'posts' => 1, 'comments' => 1, 'reactions' => 0],
            'co-author' => ['days' => 0, 'posts' => 2, 'comments' => 1, 'reactions' => 2],
            'author' => ['days' => 5, 'posts' => 2, 'comments' => 1, 'reactions' => 2],
            'top-author' => ['days' => 99, 'posts' => 99, 'comments' => 99, 'reactions' => 99],
        ]);
        [$anna, $boris, $vera] = User::factory()->count(3)->create();
        $this->assertSame(0, $anna->fresh()->level);

        $first = $this->makePost($anna);
        $this->assertSame(0, $anna->fresh()->level, 'a comment is still missing');
        Comment::create(['post_id' => $first->id, 'user_id' => $anna->id, 'body' => 'Комментарий']);
        $this->assertSame(1, $anna->fresh()->level);
        $this->assertSame('club-member', Badges::primary($anna->fresh()));

        $this->makePost($anna);
        // Her own reaction does not count.
        Like::create(['user_id' => $anna->id, 'type' => 'like', 'likeable_type' => 'post', 'likeable_id' => $first->id]);
        Like::create(['user_id' => $boris->id, 'type' => 'love', 'likeable_type' => 'post', 'likeable_id' => $first->id]);
        $this->assertSame(1, $anna->fresh()->level);
        $this->actingAs($vera)->post("/posts/{$first->id}/like")->assertRedirect();
        $this->assertSame(2, $anna->fresh()->level);

        // Days in the club are counted by the nightly run.
        $anna->forceFill(['created_at' => now()->subDays(6)])->save();
        Badges::refreshAll();
        $this->assertSame(3, $anna->fresh()->level);

        // Each new level is told under the bell.
        $kinds = $anna->notifications()->get()->where('data.kind', 'level_up')->pluck('data.badge')->all();
        $this->assertEqualsCanonicalizing(['club-member', 'co-author', 'author'], $kinds);

        // The badge goes with her name on posts.
        $this->actingAs($boris)->get("/posts/{$first->id}")
            ->assertInertia(fn ($page) => $page->where('post.data.author.badge', 'author'));
    }

    public function test_members_who_did_not_accept_the_rules_stay_plain_users()
    {
        Badges::saveThresholds(['club-member' => ['days' => 0, 'posts' => 1, 'comments' => 0, 'reactions' => 0]]);
        $user = User::factory()->create(['rules_accepted_at' => null]);
        $this->makePost($user);
        $this->assertSame(0, $user->fresh()->level);
        $this->assertNull(Badges::primary($user->fresh()));
    }

    public function test_an_admin_gives_and_takes_special_badges_which_win_over_the_level()
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $author = User::factory()->create();
        $author->forceFill(['level' => 2])->save();

        $this->actingAs($admin)->put("/admin/users/{$author->id}/badges", ['badge' => 'beta-tester', 'on' => true])->assertRedirect();
        $this->actingAs($admin)->put("/admin/users/{$author->id}/badges", ['badge' => 'club-author', 'on' => true])->assertRedirect();
        $this->actingAs($admin)->put("/admin/users/{$author->id}/badges", ['badge' => 'nonsense', 'on' => true])->assertSessionHasErrors('badge');

        $author->refresh();
        $this->assertSame(['club-author', 'beta-tester'], Badges::special($author));
        $this->assertSame('club-author', Badges::primary($author));
        $this->assertSame('badge_given', $author->notifications()->first()->data['kind']);

        $this->actingAs($admin)->get('/admin/users?badge=club-author')
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.id', $author->id));
        $this->actingAs($admin)->get('/admin/badges')
            ->assertInertia(fn ($page) => $page->component('admin/badges')->where('special.0', ['slug' => 'club-author', 'count' => 1]));
        $this->actingAs($admin)->get("/people/{$author->id}")
            ->assertInertia(fn ($page) => $page->where('profile.level', 'co-author')->where('profile.badges', ['club-author', 'beta-tester']));

        $this->actingAs($admin)->put("/admin/users/{$author->id}/badges", ['badge' => 'club-author', 'on' => false]);
        $this->assertSame('beta-tester', Badges::primary($author->fresh()));

        // The profile offers the administrator every special badge to give, and a member none.
        $this->actingAs($admin)->put("/admin/users/{$author->id}/badges", ['badge' => 'inner-circle', 'on' => true]);
        $this->actingAs($admin)->put("/admin/users/{$author->id}/badges", ['badge' => 'ambassador', 'on' => true]);
        $this->assertSame(['inner-circle', 'ambassador', 'beta-tester'], Badges::special($author->fresh()));
        $this->actingAs($admin)->get("/people/{$author->id}")
            ->assertInertia(fn ($page) => $page->where('profile.special_badges', Badges::SPECIAL));
        $this->actingAs($author)->get("/people/{$admin->id}")
            ->assertInertia(fn ($page) => $page->where('profile.special_badges', null));

        // Members cannot give badges.
        $this->actingAs($author)->put("/admin/users/{$admin->id}/badges", ['badge' => 'founder', 'on' => true])->assertForbidden();
    }

    public function test_an_admin_changes_what_the_levels_ask_for()
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $member = User::factory()->create();
        $this->makePost($member);
        $this->assertSame(0, $member->fresh()->level);

        $thresholds = Badges::thresholds();
        $thresholds['club-member'] = ['days' => 0, 'posts' => 1, 'comments' => 0, 'reactions' => 0];
        $this->actingAs($admin)->put('/admin/badges', ['thresholds' => $thresholds])->assertRedirect();

        $this->assertSame(1, $member->fresh()->level);
        $this->assertSame(0, Badges::thresholds()['club-member']['days']);
    }
}
