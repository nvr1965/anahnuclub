<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroupSearchAndPruneTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, ?string $created = null, array $extra = []): Group
    {
        $group = Group::create(['name' => $name, 'slug' => Str::slug($name), 'owner_id' => User::factory()->create()->id] + $extra);
        if ($created) {
            $group->forceFill(['created_at' => $created])->save();
        }

        return $group;
    }

    public function test_the_groups_page_filters_by_name_and_description(): void
    {
        $viewer = User::factory()->create();
        $this->group('Haifa friends');
        $this->group('Cooking club', null, ['description' => 'Recipes from Haifa kitchens']);
        $this->group('Tel Aviv runners');

        $this->actingAs($viewer)->get('/groups')
            ->assertInertia(fn ($page) => $page->component('groups/index')->has('groups.data', 3)->where('q', ''));

        $this->actingAs($viewer)->get('/groups?q=haifa')
            ->assertInertia(fn ($page) => $page->has('groups.data', 2)->where('q', 'haifa'));

        $this->actingAs($viewer)->get('/groups?q=runn')
            ->assertInertia(fn ($page) => $page->has('groups.data', 1)->where('groups.data.0.name', 'Tel Aviv runners'));

        // A percent sign is searched as text, not as a wildcard.
        $this->actingAs($viewer)->get('/groups?q=%25')
            ->assertInertia(fn ($page) => $page->has('groups.data', 0));
    }

    public function test_only_old_groups_without_any_content_are_stale(): void
    {
        $empty = $this->group('Old and empty', now()->subYears(2)->toDateTimeString());
        $young = $this->group('Young and empty', now()->subMonths(3)->toDateTimeString());
        $withPost = $this->group('Old with a post', now()->subYears(2)->toDateTimeString());
        Post::create(['user_id' => $withPost->owner_id, 'group_id' => $withPost->id, 'body' => 'hello']);
        $withAlbum = $this->group('Old with an album', now()->subYears(2)->toDateTimeString());
        DB::table('albums')->insert(['group_id' => $withAlbum->id, 'name' => 'Photos', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([$empty->id], Group::query()->stale()->pluck('id')->all());
    }

    public function test_the_command_only_lists_without_force_and_deletes_with_it(): void
    {
        $empty = $this->group('Old and empty', now()->subYears(2)->toDateTimeString());
        $kept = $this->group('Young and empty');

        $this->artisan('groups:prune-stale')->expectsOutputToContain('Found 1 groups')->assertSuccessful();
        $this->assertNotNull(Group::find($empty->id));

        $this->artisan('groups:prune-stale', ['--force' => true])->expectsOutputToContain('Deleted 1 groups')->assertSuccessful();
        $this->assertNull(Group::find($empty->id));
        $this->assertNotNull(Group::find($kept->id));
    }

    public function test_an_administrator_sees_and_deletes_stale_groups(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $empty = $this->group('Old and empty', now()->subYears(2)->toDateTimeString());
        $active = $this->group('Old with a post', now()->subYears(2)->toDateTimeString());
        Post::create(['user_id' => $active->owner_id, 'group_id' => $active->id, 'body' => 'hello']);

        $this->actingAs(User::factory()->create())->post('/admin/groups/prune-stale')->assertForbidden();

        $this->actingAs($admin)->get('/admin/groups?stale=1')
            ->assertInertia(fn ($page) => $page->where('stale_count', 1)->has('groups.data', 1)->where('groups.data.0.id', $empty->id));

        $this->actingAs($admin)->post('/admin/groups/prune-stale')->assertRedirect('/admin/groups');
        $this->assertNull(Group::find($empty->id));
        $this->assertNotNull(Group::find($active->id));
    }

    public function test_the_groups_page_sorts_by_name_or_by_creation_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $b = Group::create(['name' => 'Бета', 'slug' => 'b']);
        $a = Group::create(['name' => 'Альфа', 'slug' => 'a']);
        $b->forceFill(['created_at' => now()->subDays(2)])->save();
        $a->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($admin)->get('/admin/groups')->assertInertia(fn ($p) => $p->where('sort', 'name')->where('groups.data.0.name', 'Альфа'));
        $this->get('/admin/groups?sort=newest')->assertInertia(fn ($p) => $p->where('groups.data.0.name', 'Альфа'));
        $this->get('/admin/groups?sort=oldest')->assertInertia(fn ($p) => $p->where('groups.data.0.name', 'Бета'));
    }
}
