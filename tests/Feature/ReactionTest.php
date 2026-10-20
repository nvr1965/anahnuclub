<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_picks_changes_and_takes_back_a_reaction()
    {
        [$anna, $boris, $vera] = User::factory()->count(3)->create();
        $post = Post::create(['user_id' => $anna->id, 'type' => 'status', 'body' => 'Пост', 'audience' => 'public']);
        $like = fn (User $u, ?string $type = null) => $this->actingAs($u)->post("/posts/{$post->id}/like", $type ? ['type' => $type] : [])->assertRedirect();
        $show = fn (User $u) => $this->actingAs($u)->get("/posts/{$post->id}");

        $like($boris, 'love');
        $like($vera, 'haha');
        $like($anna);
        $show($boris)->assertInertia(fn ($page) => $page
            ->where('post.data.likes_count', 3)
            ->where('post.data.my_reaction', 'love')
            ->where('post.data.liked', true)
            ->has('post.data.reactions', 3));

        // Another face replaces the first; the same face again takes it back.
        $like($boris, 'haha');
        $show($boris)->assertInertia(fn ($page) => $page->where('post.data.my_reaction', 'haha')->where('post.data.reactions.0', 'haha')->where('post.data.likes_count', 3));
        $like($boris);
        $show($boris)->assertInertia(fn ($page) => $page->where('post.data.my_reaction', null)->where('post.data.likes_count', 2));

        $this->actingAs($vera)->post("/posts/{$post->id}/like", ['type' => 'nonsense'])->assertSessionHasErrors('type');
    }
}
