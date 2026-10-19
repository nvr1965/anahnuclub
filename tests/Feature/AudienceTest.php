<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AudienceTest extends TestCase
{
    use RefreshDatabase;

    private function befriend(User $a, User $b): void
    {
        DB::table('friendships')->insert(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted' => true]);
    }

    /** @return array{User, User, User, User} author, two friends, a stranger */
    private function people(): array
    {
        [$anna, $boris, $vera, $gleb] = User::factory()->count(4)->create();
        $this->befriend($anna, $boris);
        $this->befriend($vera, $anna);

        return [$anna, $boris, $vera, $gleb];
    }

    private function sees(?User $viewer, Post $post): bool
    {
        if ($viewer) {
            $this->actingAs($viewer);
        } else {
            auth()->logout();
        }

        return $this->get("/posts/{$post->id}")->status() === 200;
    }

    public function test_each_audience_reaches_the_right_people()
    {
        [$anna, $boris, $vera, $gleb] = $this->people();

        $make = function (array $data) use ($anna) {
            $this->actingAs($anna)->post('/posts', ['body' => 'Пост'] + $data)->assertRedirect();

            return Post::latest('id')->firstOrFail();
        };

        $public = $make(['audience' => 'public']);
        $this->assertTrue($this->sees(null, $public));
        $this->assertTrue($this->sees($gleb, $public));

        $friends = $make(['audience' => 'friends']);
        $this->assertFalse($this->sees(null, $friends));
        $this->assertFalse($this->sees($gleb, $friends));
        $this->assertTrue($this->sees($boris, $friends));
        $this->assertTrue($this->sees($anna, $friends));

        $except = $make(['audience' => 'except', 'audience_ids' => [$boris->id]]);
        $this->assertFalse($this->sees($boris, $except));
        $this->assertTrue($this->sees($vera, $except));
        $this->assertFalse($this->sees($gleb, $except));

        // A stranger named among the chosen is dropped: only friends can be picked.
        $specific = $make(['audience' => 'specific', 'audience_ids' => [$vera->id, $gleb->id]]);
        $this->assertTrue($this->sees($vera, $specific));
        $this->assertFalse($this->sees($boris, $specific));
        $this->assertFalse($this->sees($gleb, $specific));

        $mine = $make(['audience' => 'only_me']);
        $this->assertFalse($this->sees($boris, $mine));
        $this->assertTrue($this->sees($anna, $mine));
    }

    public function test_hidden_posts_stay_out_of_feeds_and_cannot_be_liked_or_commented()
    {
        [$anna, $boris, , $gleb] = $this->people();
        $post = Post::create(['user_id' => $anna->id, 'body' => 'Только друзьям', 'audience' => 'friends']);

        $this->actingAs($gleb)->get("/people/{$anna->id}")
            ->assertInertia(fn ($page) => $page->where('posts.data', []));
        $this->post("/posts/{$post->id}/like")->assertNotFound();
        $this->post("/posts/{$post->id}/comments", ['body' => 'Эй'])->assertNotFound();

        $this->actingAs($boris)->get("/people/{$anna->id}")
            ->assertInertia(fn ($page) => $page->has('posts.data', 1));
    }

    public function test_the_author_changes_the_audience_and_sets_a_default()
    {
        [$anna, $boris] = $this->people();
        $this->actingAs($anna)->post('/posts', ['body' => 'Пост', 'audience' => 'only_me', 'audience_default' => true]);
        $post = Post::latest('id')->firstOrFail();
        $this->assertSame('only_me', $anna->fresh()->default_audience);

        // With no audience given, the default is used.
        $this->post('/posts', ['body' => 'Ещё']);
        $this->assertSame('only_me', Post::latest('id')->first()->audience);

        $this->actingAs($boris)->patch("/posts/{$post->id}/audience", ['audience' => 'public'])->assertForbidden();
        $this->actingAs($anna)->patch("/posts/{$post->id}/audience", ['audience' => 'specific', 'audience_ids' => [$boris->id]])->assertRedirect();
        $this->assertTrue($this->sees($boris, $post));
        $this->actingAs($anna)->get("/posts/{$post->id}")
            ->assertInertia(fn ($page) => $page->where('post.data.audience', 'specific')->where('post.data.audience_ids', [$boris->id]));
    }

    public function test_profile_details_follow_their_visibility()
    {
        [$anna, $boris, , $gleb] = $this->people();
        $anna->update(['location' => 'Хайфа', 'website' => 'https://anna.example', 'birthdate' => '1980-05-01']);

        $this->actingAs($anna)->patch('/profile/about', [
            'name' => $anna->name,
            'location' => 'Хайфа',
            'website' => 'https://anna.example',
            'birthdate' => '1980-05-01',
            'privacy' => ['location' => 'friends', 'birthdate' => 'only_me', 'friends' => 'only_me', 'nonsense' => 'public'],
        ])->assertRedirect();
        $this->assertSame(['location' => 'friends', 'birthdate' => 'only_me', 'friends' => 'only_me'], $anna->fresh()->privacy);

        $this->actingAs($gleb)->get("/people/{$anna->id}")->assertInertia(fn ($page) => $page
            ->where('profile.location', null)
            ->where('profile.birthdate', null)
            ->where('profile.website', 'https://anna.example')
            ->where('profile.friends_hidden', true)
            ->where('profile.privacy', null)
            ->where('friends', []));

        $this->actingAs($boris)->get("/people/{$anna->id}")->assertInertia(fn ($page) => $page
            ->where('profile.location', 'Хайфа')
            ->where('profile.birthdate', null));

        $this->actingAs($anna)->get("/people/{$anna->id}")->assertInertia(fn ($page) => $page
            ->where('profile.birthdate', '1980-05-01')
            ->where('profile.friends_hidden', false)
            ->where('profile.privacy.birthdate', 'only_me'));
    }

    public function test_the_friend_picker_lists_only_friends()
    {
        [$anna, $boris, $vera] = $this->people();
        $this->actingAs($anna)->getJson('/friends/list')->assertOk()->assertJsonCount(2)
            ->assertJsonFragment(['id' => $boris->id])->assertJsonFragment(['id' => $vera->id]);
    }
}
