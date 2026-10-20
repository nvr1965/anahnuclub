<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_listed_only_for_their_friends_and_other_admins()
    {
        $admin = User::factory()->create(['name' => 'Админ']);
        $admin->forceFill(['is_admin' => true])->save();
        [$friend, $stranger] = User::factory()->count(2)->create();
        DB::table('friendships')->insert(['user_id' => $admin->id, 'friend_id' => $friend->id, 'accepted' => true]);
        $ids = fn ($page) => collect($page->toArray()['props']['people']['data'])->pluck('id');

        $this->actingAs($stranger)->get('/people')->assertInertia(fn ($page) => $this->assertNotContains($admin->id, $ids($page)));
        $this->get("/people/{$friend->id}?tab=friends")->assertInertia(fn ($page) => $page->has('friends', 0)->where('profile.friends_count', 0));

        $this->actingAs($friend)->get('/people')->assertInertia(fn ($page) => $this->assertContains($admin->id, $ids($page)));
        $this->get("/people/{$friend->id}?tab=friends")->assertInertia(fn ($page) => $page->where('friends.0.id', $admin->id));
    }

    public function test_invitation_links_show_a_picture_in_the_inviters_language()
    {
        $this->get('/?lang=he')->assertOk()
            ->assertSee('images/invite/he.jpg', false)
            ->assertSee('og:image:width', false);
        $this->assertSame('he', session('locale'));
    }
}
