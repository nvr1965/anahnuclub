<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAndFriendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_profile_shows_the_member_and_their_posts_but_not_private_group_posts()
    {
        $member = User::factory()->create(['about' => 'Люблю Хайфу', 'location' => 'Haifa']);
        $private = Group::create(['name' => 'Secret', 'slug' => 'secret', 'is_private' => true]);
        Post::create(['user_id' => $member->id, 'body' => 'Public post']);
        Post::create(['user_id' => $member->id, 'group_id' => $private->id, 'body' => 'Private post']);

        $this->actingAs(User::factory()->create())->get("/people/{$member->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('people/show')
            ->where('profile.about', 'Люблю Хайфу')
            ->where('profile.friendship', 'none')
            ->has('posts.data', 1)
            ->where('posts.data.0.body', 'Public post')
        );
    }

    public function test_members_edit_their_details_and_upload_pictures()
    {
        Storage::fake('public');
        $member = User::factory()->create();

        $this->actingAs($member)->patch('/profile/about', ['name' => 'Ника', 'about' => 'Привет', 'location' => 'Тель-Авив', 'country' => 'IL', 'website' => 'https://example.com', 'birthdate' => '1990-05-01', 'gender' => 'female'])->assertRedirect();
        $this->assertSame('Тель-Авив', $member->fresh()->location);
        $this->assertSame('IL', $member->fresh()->country);
        $this->patch('/profile/about', ['name' => 'Ника', 'country' => 'Israel'])->assertSessionHasErrors('country');
        $this->assertSame('1990-05-01', $member->fresh()->birthdate->format('Y-m-d'));

        $this->post('/profile/avatar', ['image' => UploadedFile::fake()->image('me.jpg', 1200, 900)])->assertRedirect();
        $path = $member->fresh()->avatar_path;
        Storage::disk('public')->assertExists($path);
        $this->assertSame([400, 400], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));

        $this->post('/profile/cover', ['image' => UploadedFile::fake()->image('cover.png', 2000, 600)])->assertRedirect();
        $this->assertNotNull($member->fresh()->cover_path);

        $this->post('/profile/avatar', ['remove' => true])->assertRedirect();
        $this->assertNull($member->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);

        $this->post('/profile/avatar', ['image' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('image');
    }

    public function test_friend_requests_can_be_sent_accepted_and_ended()
    {
        [$anna, $boris] = User::factory()->count(2)->create();

        $this->actingAs($anna)->post("/people/{$boris->id}/friend")->assertRedirect();
        $this->assertSame('sent', $anna->friendshipWith($boris));
        $this->assertSame('received', $boris->friendshipWith($anna));

        $this->actingAs($boris)->get('/feed')->assertInertia(fn ($page) => $page->where('counts.friend_requests', 1));
        $this->get('/friends')->assertInertia(fn ($page) => $page->where('requests.0.id', $anna->id));

        $this->post("/people/{$anna->id}/friend")->assertRedirect();
        $this->assertSame('friends', $anna->friendshipWith($boris));
        $this->assertEquals([$boris->id], $anna->friendIds()->all());
        $this->assertEquals([$anna->id], $boris->friendIds()->all());

        $this->actingAs($anna)->delete("/people/{$boris->id}/friend")->assertRedirect();
        $this->assertSame('none', $boris->friendshipWith($anna));
    }

    public function test_nobody_can_befriend_themselves()
    {
        $anna = User::factory()->create();
        $this->actingAs($anna)->post("/people/{$anna->id}/friend")->assertStatus(422);
    }

    public function test_the_members_page_lists_everyone_with_a_name_search()
    {
        $me = User::factory()->create(['name' => 'Me']);
        $friend = User::factory()->create(['name' => 'Ольга', 'avatar_path' => null]);
        User::factory()->create(['name' => 'Борис']);
        User::factory()->create(['name' => 'Spammer', 'blocked' => true]);
        $this->actingAs($me)->post("/people/{$friend->id}/friend");

        $this->actingAs($me)->get('/people')->assertOk()->assertInertia(fn ($page) => $page
            ->component('people/index')
            ->where('total', 2)
            ->has('people.data', 2)
        );
        $this->actingAs($me)->get('/people?q=Ольг')->assertInertia(fn ($page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Ольга')
            ->where('people.data.0.friendship', 'sent')
        );
    }

    public function test_members_seen_in_the_last_minutes_show_as_online()
    {
        $here = User::factory()->create(['last_seen_at' => now()->subMinute()]);
        $away = User::factory()->create(['last_seen_at' => now()->subHour()]);

        $this->actingAs($here)->get('/people/'.$away->id)->assertInertia(fn ($page) => $page->where('profile.online', false));
        $this->get('/people/'.$here->id)->assertInertia(fn ($page) => $page->where('profile.online', true));
        $this->get('/people')->assertInertia(fn ($page) => $page->where('people.data', fn ($rows) => collect($rows)->firstWhere('id', $away->id)['online'] === false));
    }

    public function test_friends_on_the_site_now_come_first()
    {
        $me = User::factory()->create();
        $anna = User::factory()->create(['name' => 'Anna', 'last_seen_at' => now()->subDay()]);
        $zoe = User::factory()->create(['name' => 'Zoe', 'last_seen_at' => now()->subMinute()]);
        $never = User::factory()->create(['name' => 'Boris', 'last_seen_at' => null]);
        foreach ([$anna, $zoe, $never] as $friend) {
            DB::table('friendships')->insert(['user_id' => $me->id, 'friend_id' => $friend->id, 'accepted' => true]);
        }

        $this->actingAs($me)->get('/friends')->assertInertia(fn ($page) => $page->where('friends.0.id', $zoe->id)->where('friends.1.id', $anna->id));
        $this->get('/people/'.$me->id)->assertInertia(fn ($page) => $page->where('friends.0.id', $zoe->id)->where('friends.2.id', $never->id));
    }

    public function test_members_are_filtered_by_country_and_language()
    {
        $me = User::factory()->create();
        $il = User::factory()->create(['country' => 'IL', 'locale' => 'he']);
        $hidden = User::factory()->create(['country' => 'IL', 'locale' => 'ru', 'privacy' => ['country' => 'only_me']]);
        $ru = User::factory()->create(['country' => 'RU', 'locale' => 'ru']);

        $this->actingAs($me)->get('/people?country=IL')->assertInertia(fn ($page) => $page
            ->where('people.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$il->id])
            ->where('countries.IL', 1)->where('countries.RU', 1));
        $this->get('/people?lang=ru')->assertInertia(fn ($page) => $page
            ->where('people.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === collect([$hidden->id, $ru->id])->sort()->values()->all()));
    }

    public function test_members_pick_their_default_language_in_settings()
    {
        $me = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($me)->get('/settings/profile')->assertInertia(fn ($page) => $page->where('locale', 'ru')->where('canDelete', true));
        $this->patch('/settings/profile', ['name' => $me->name, 'email' => $me->email, 'locale' => 'he'])->assertSessionHasNoErrors();
        $this->assertSame(['he', 'he'], [$me->fresh()->locale, $me->fresh()->ui_locale]);
        $this->get('/feed')->assertInertia(fn ($page) => $page->where('locale', 'he'));
        $this->patch('/settings/profile', ['name' => $me->name, 'email' => $me->email, 'locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_the_default_language_is_also_set_in_the_profile_dialog()
    {
        $me = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($me)->get('/people/'.$me->id)->assertInertia(fn ($page) => $page->where('profile.locale', 'ru'));
        $this->patch('/profile/about', ['name' => $me->name, 'locale' => 'he'])->assertSessionHasNoErrors();
        $this->assertSame(['he', 'he'], [$me->fresh()->locale, $me->fresh()->ui_locale]);
    }
}
