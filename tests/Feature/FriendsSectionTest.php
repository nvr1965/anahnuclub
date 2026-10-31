<?php

namespace Tests\Feature;

use App\Models\FriendList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FriendsSectionTest extends TestCase
{
    use RefreshDatabase;

    private function befriend(User $a, User $b): void
    {
        DB::table('friendships')->insert(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_friends_of_friends_are_suggested_first_with_mutual_friends()
    {
        $me = User::factory()->create();
        [$anna, $boris, $olga, $stranger] = User::factory()->count(4)->create(['avatar_path' => 'a.jpg', 'last_seen_at' => now()])->all();
        $this->befriend($me, $anna);
        $this->befriend($boris, $me);
        // Olga is friends with both of my friends.
        $this->befriend($anna, $olga);
        $this->befriend($olga, $boris);

        $this->actingAs($me)->get('/friends')->assertOk()->assertInertia(fn ($page) => $page
            ->component('friends/index')
            ->where('suggestions.0.id', $olga->id)
            ->where('suggestions.0.mutual', 2)
            ->has('suggestions.0.mutual_people', 2)
            ->where('suggestions.1.id', $stranger->id)
            ->where('suggestions.1.mutual', 0)
            ->has('suggestions', 2)
            ->where('menu.friends', 2));
    }

    public function test_a_removed_suggestion_is_not_suggested_again()
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['avatar_path' => 'a.jpg']);

        $this->actingAs($me)->get('/friends/suggestions')->assertInertia(fn ($page) => $page->component('friends/browse')->where('people.0.id', $other->id));
        $this->post("/people/{$other->id}/dismiss")->assertRedirect();
        $this->get('/friends/suggestions')->assertInertia(fn ($page) => $page->has('people', 0));
    }

    public function test_request_and_all_friends_pages_show_a_preview_of_the_chosen_person()
    {
        $me = User::factory()->create();
        $asker = User::factory()->create(['about' => 'Привет']);
        $friend = User::factory()->create();
        DB::table('friendships')->insert(['user_id' => $asker->id, 'friend_id' => $me->id, 'accepted' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->befriend($me, $friend);

        $this->actingAs($me)->get("/friends/requests?id={$asker->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('kind', 'requests')
            ->where('people.0.id', $asker->id)
            ->where('preview.id', $asker->id)
            ->where('preview.about', 'Привет')
            ->where('preview.friendship', 'received')
            ->where('menu.requests', 1));

        $this->get('/friends/all')->assertInertia(fn ($page) => $page->where('people.0.id', $friend->id)->where('preview', null));
    }

    public function test_birthdays_list_friends_who_share_their_date_nearest_first()
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(12, 0));
        $me = User::factory()->create();
        $today = User::factory()->create(['birthdate' => '1980-10-31']);
        $soon = User::factory()->create(['birthdate' => '1990-11-05']);
        $hidden = User::factory()->create(['birthdate' => '1990-11-02', 'privacy' => ['birthdate' => 'only_me']]);
        $notFriend = User::factory()->create(['birthdate' => '1990-11-01']);
        foreach ([$today, $soon, $hidden] as $u) {
            $this->befriend($me, $u);
        }

        $this->actingAs($me)->get('/friends/birthdays')->assertOk()->assertInertia(fn ($page) => $page
            ->component('friends/birthdays')
            ->has('people', 2)
            ->where('people.0.id', $today->id)
            ->where('people.0.days', 0)
            ->where('people.0.age', 46)
            ->where('people.1.id', $soon->id)
            ->where('people.1.days', 5));
    }

    public function test_custom_lists_hold_friends_only_and_belong_to_their_owner()
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $this->befriend($me, $friend);

        $this->actingAs($me)->post('/friends/lists', ['name' => 'Семья'])->assertRedirect();
        $list = FriendList::firstOrFail();
        $this->post("/friends/lists/{$list->id}/people/{$friend->id}")->assertRedirect();
        $this->post("/friends/lists/{$list->id}/people/{$stranger->id}")->assertStatus(422);

        $this->get('/friends/lists')->assertInertia(fn ($page) => $page
            ->where('lists.0.name', 'Семья')
            ->where('lists.0.count', 1)
            ->where('current.members.0.id', $friend->id)
            ->has('current.others', 0));

        $this->actingAs($stranger)->delete("/friends/lists/{$list->id}")->assertNotFound();

        // Ending the friendship takes them out of the list.
        $this->actingAs($me)->delete("/people/{$friend->id}/friend");
        $this->assertSame(0, $list->members()->count());
    }
}
