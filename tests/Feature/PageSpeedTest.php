<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Album;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PageSpeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_interface_strings_come_again_only_when_the_page_lacks_them()
    {
        $user = User::factory()->create();
        $version = null;

        // The first load of the site brings them, with their version.
        $this->actingAs($user)->get('/feed')->assertOk()->assertInertia(function ($page) use (&$version) {
            $page->where('translations', fn ($t) => count($t) > 100)->has('translations_version');
            $version = $page->toArray()['props']['translations_version'];
        });

        $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];

        // Moving on with the same version: none.
        $this->withHeaders($inertia + ['X-UI-Strings' => $version])->get('/people')->assertOk()
            ->assertJsonPath('props.translations', null)
            ->assertJsonPath('props.translations_version', $version);

        // Another version (an update, another language): all of them again.
        $strings = $this->withHeaders($inertia + ['X-UI-Strings' => 'he-000000000000'])->get('/people')->assertOk()->json('props.translations');
        $this->assertGreaterThan(100, count($strings));
    }

    public function test_the_feed_pages_on_without_counting_every_post()
    {
        $user = User::factory()->create();
        foreach (range(1, 21) as $i) {
            Post::create(['user_id' => $user->id, 'body' => "Post $i"]);
        }

        $this->actingAs($user)->get('/feed')->assertOk()->assertInertia(fn ($page) => $page
            ->has('posts.data', 20)
            ->where('posts.links.next', fn ($url) => str_contains($url, 'page=2'))
            ->missing('posts.meta.total'));
        $this->get('/feed?page=2')->assertOk()->assertInertia(fn ($page) => $page->has('posts.data', 1)->where('posts.links.next', null));
    }

    public function test_the_side_column_shows_the_latest_pictures_the_reader_may_see()
    {
        $author = User::factory()->create();
        $album = Album::create(['user_id' => $author->id, 'name' => 'Timeline']);
        $open = Post::create(['user_id' => $author->id, 'body' => 'For all']);
        $closed = Post::create(['user_id' => $author->id, 'body' => 'Friends only', 'audience' => 'friends']);

        $photo = fn (?Post $post, string $name, string $at) => Photo::create([
            'album_id' => $album->id, 'user_id' => $author->id, 'post_id' => $post?->id,
            'path' => "photos/$name.jpg", 'thumb_path' => "photos/thumbs/$name.jpg",
        ])->forceFill(['created_at' => $at])->save();

        $photo(null, 'loose', '2026-01-01 10:00:00');
        $photo($open, 'open', '2026-01-02 10:00:00');
        // More hidden pictures than one look at the newest takes in: the visible ones behind them still show.
        foreach (range(1, 60) as $i) {
            $photo($closed, "closed$i", '2026-02-01 10:00:00');
        }

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->has('sidebar.photos', 2)
            ->where('sidebar.photos.0.thumb', fn ($url) => str_ends_with($url, 'photos/thumbs/open.jpg'))
            ->where('sidebar.photos.1.thumb', fn ($url) => str_ends_with($url, 'photos/thumbs/loose.jpg')));

        // The author sees their own friends-only pictures too, newest first, six at most.
        $this->actingAs($author)->get('/feed')->assertOk()->assertInertia(fn ($page) => $page
            ->has('sidebar.photos', 6)
            ->where('sidebar.photos.0.thumb', fn ($url) => str_contains($url, 'photos/thumbs/closed')));
    }

    public function test_lookups_by_foreign_key_have_indexes()
    {
        $this->assertTrue(Schema::hasIndex('posts', 'perf_posts_created_at_index'));
        $this->assertTrue(Schema::hasIndex('comments', 'perf_comments_post_id_created_at_index'));
        $this->assertTrue(Schema::hasIndex('friendships', 'perf_friendships_friend_id_index'));
    }
}
