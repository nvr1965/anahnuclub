<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Group;
use App\Models\Language;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    public function test_only_administrators_can_open_the_admin_pages()
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        foreach (['/admin', '/admin/users', '/admin/posts', '/admin/groups', '/admin/languages'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_an_administrator_can_block_and_promote_users_but_not_themselves()
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/users/{$user->id}", ['blocked' => true, 'is_admin' => true]);
        $this->assertTrue($user->fresh()->blocked);
        $this->assertTrue($user->fresh()->is_admin);

        $this->patch("/admin/users/{$this->admin->id}", ['is_admin' => false])->assertStatus(422);
        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_delete_as_spam_blocks_the_user_and_removes_their_posts_and_comments()
    {
        $spammer = User::factory()->create();
        $post = Post::create(['user_id' => $spammer->id, 'body' => 'Купите таблетки']);
        $other = Post::create(['user_id' => $this->admin->id, 'body' => 'Нормальный пост']);
        Comment::create(['post_id' => $other->id, 'user_id' => $spammer->id, 'body' => 'спам']);

        $this->actingAs($this->admin)->post("/admin/users/{$spammer->id}/purge")->assertRedirect();

        $this->assertTrue($spammer->fresh()->blocked);
        $this->assertNull(Post::find($post->id));
        $this->assertSame(0, Comment::where('user_id', $spammer->id)->count());
        $this->assertNotNull(Post::find($other->id));
    }

    public function test_posts_can_be_found_and_deleted_in_bulk()
    {
        $a = Post::create(['user_id' => $this->admin->id, 'body' => 'Essay writing service']);
        $b = Post::create(['user_id' => $this->admin->id, 'body' => 'Привет']);

        $this->actingAs($this->admin)->get('/admin/posts?q=essay')
            ->assertInertia(fn ($page) => $page->component('admin/posts')->has('posts.data', 1));

        $this->delete('/admin/posts', ['ids' => [$a->id]])->assertRedirect();
        $this->assertNull(Post::find($a->id));
        $this->assertNotNull(Post::find($b->id));
    }

    public function test_a_hidden_group_and_its_posts_disappear_for_members()
    {
        $group = Group::create(['name' => 'Spam group', 'slug' => 'spam-group', 'owner_id' => $this->admin->id]);
        Post::create(['user_id' => $this->admin->id, 'group_id' => $group->id, 'body' => 'реклама']);
        $member = User::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/groups/{$group->id}", ['published' => false])->assertRedirect();

        $this->actingAs($member)->get('/groups/spam-group')->assertNotFound();
        $this->get('/feed')->assertInertia(fn ($page) => $page->has('posts.data', 0));
    }

    public function test_languages_can_be_switched_off_but_not_the_last_or_fallback_one()
    {
        $this->actingAs($this->admin)->patch('/admin/languages/ar', ['enabled' => false])->assertRedirect();
        $this->assertFalse(Language::find('ar')->enabled);

        $this->patch('/admin/languages/en', ['enabled' => false])->assertStatus(422);
    }

    public function test_the_main_administrator_cannot_be_blocked_demoted_or_deleted()
    {
        $other = User::factory()->create();
        $other->forceFill(['is_admin' => true])->save();
        $this->assertTrue($this->admin->isSuperAdmin());
        $this->assertFalse($other->isSuperAdmin());

        $this->actingAs($other)->patch("/admin/users/{$this->admin->id}", ['is_admin' => false])->assertStatus(422);
        $this->patch("/admin/users/{$this->admin->id}", ['blocked' => true])->assertStatus(422);
        $this->get('/admin/users')->assertInertia(fn ($page) => $page->where('users.data.1.is_super', true));
        $this->assertTrue($this->admin->fresh()->is_admin);

        $this->actingAs($this->admin)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($this->admin->fresh());

        // A named address picks someone else.
        config(['app.super_admin_email' => $other->email]);
        $this->assertTrue($other->isSuperAdmin());
        $this->assertFalse($this->admin->isSuperAdmin());
    }

    public function test_english_is_marked_as_the_base_language()
    {
        $this->actingAs($this->admin)->get('/admin/languages')->assertInertia(fn ($page) => $page
            ->where('languages', fn ($rows) => collect($rows)->firstWhere('code', 'en')['base'] === true && collect($rows)->firstWhere('code', 'ru')['base'] === false));
        $this->delete('/admin/languages/en')->assertStatus(422);
    }
}
