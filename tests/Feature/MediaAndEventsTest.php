<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAndEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_a_post_can_carry_pictures_which_land_in_the_timeline_album()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post('/posts', ['images' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('b.png', 300, 300)]])->assertSessionHasNoErrors()->assertRedirect();

        $post = Post::firstOrFail();
        $this->assertSame('photo', $post->type);
        $this->assertSame(2, $post->photos()->count());
        $this->assertSame('timeline', $post->photos()->first()->album->kind);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($post->photos()->first()->path));
        $this->assertSame(2048, $width);

        $this->get('/feed')->assertInertia(fn ($page) => $page->has('posts.data.0.photos', 2));

        $this->delete("/posts/{$post->id}")->assertRedirect();
        $this->assertSame(0, Photo::count());
    }

    public function test_a_post_can_carry_a_video_shot_with_the_camera()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post('/posts', [
            'body' => "Шаббат шалом\nиз Хайфы",
            'video' => UploadedFile::fake()->create('video-1.webm', 800, 'video/webm'),
            'video_thumb' => UploadedFile::fake()->image('thumb.jpg', 640, 360),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $post = Post::with('video')->firstOrFail();
        $this->assertSame('Шаббат шалом', $post->video->title);
        $this->assertSame('file', $post->video->provider);
        Storage::disk('public')->assertExists($post->video->path);
        $this->assertNotNull($post->video->thumb_path);
        $this->get('/feed')->assertInertia(fn ($page) => $page->where('posts.data.0.video.id', $post->video_id));

        $this->post('/posts', ['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('video');
    }

    public function test_members_make_albums_upload_and_delete_photos()
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post('/albums', ['name' => 'Хайфа'])->assertRedirect();
        $album = Album::firstOrFail();

        $this->post("/albums/{$album->id}/photos", ['images' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')], 'share' => true])->assertRedirect();
        $this->assertSame(2, $album->photos()->count());
        $this->assertSame(2, Post::firstOrFail()->photos()->count());

        $this->get("/albums/{$album->id}")->assertInertia(fn ($page) => $page->component('albums/show')->has('photos', 2)->where('album.mine', true));
        $this->get("/people/{$member->id}?tab=photos")->assertInertia(fn ($page) => $page->where('albums.0.photos_count', 2));

        $photo = $album->photos()->first();
        $this->actingAs(User::factory()->create())->delete("/photos/{$photo->id}")->assertForbidden();
        $this->actingAs($member)->delete("/photos/{$photo->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($photo->path);

        $this->delete("/albums/{$album->id}")->assertRedirect();
        $this->assertSame(0, Photo::count());
    }

    public function test_videos_are_added_from_youtube_links_or_uploaded()
    {
        Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Shabbat songs'])]);
        $member = User::factory()->create();

        $this->actingAs($member)->post('/videos', ['url' => 'https://youtu.be/AGPx-ekqZEo'])->assertRedirect();
        $video = Video::firstOrFail();
        $this->assertSame(['youtube', 'AGPx-ekqZEo', 'Shabbat songs'], [$video->provider, $video->provider_id, $video->title]);
        $this->assertSame($video->id, Post::firstOrFail()->video_id);
        $this->get("/videos/{$video->id}")->assertInertia(fn ($page) => $page->where('video.embed_url', 'https://www.youtube-nocookie.com/embed/AGPx-ekqZEo'));

        $this->post('/videos', ['url' => 'https://example.com/clip'])->assertSessionHasErrors('url');

        $this->post('/videos', ['file' => UploadedFile::fake()->create('trip.mp4', 500, 'video/mp4'), 'title' => 'Trip', 'share' => false])->assertRedirect();
        $file = Video::where('provider', 'file')->firstOrFail();
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame(1, Post::count());
    }

    public function test_events_take_answers_and_tell_the_organiser()
    {
        $organiser = User::factory()->create();
        $guest = User::factory()->create();

        $this->actingAs($organiser)->post('/events', ['title' => 'Пикник', 'starts_at' => now()->addDays(3)->toIso8601String(), 'location' => 'Парк Яркон'])->assertRedirect();
        $event = Event::firstOrFail();
        $this->assertSame('going', $event->attendees()->first()->pivot->status);

        $this->actingAs($guest)->post("/events/{$event->id}/respond", ['status' => 'interested'])->assertRedirect();
        $this->post("/events/{$event->id}/respond", ['status' => 'going'])->assertRedirect();
        $this->get('/events')->assertInertia(fn ($page) => $page->where('upcoming.0.going_count', 2)->where('upcoming.0.my_status', 'going'));

        // Changing the answer replaces the notice instead of adding one.
        $this->assertSame(['event_going'], $organiser->notifications()->get()->pluck('data.kind')->all());
    }

    public function test_likes_and_comments_notify_the_author_and_the_bell_opens_them()
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'body' => 'Шалом']);

        $this->actingAs($fan)->post("/posts/{$post->id}/like");
        $this->post("/posts/{$post->id}/like");
        $this->post("/posts/{$post->id}/like");
        $this->post("/posts/{$post->id}/comments", ['body' => 'Класс']);
        $this->actingAs($author)->post("/posts/{$post->id}/like");

        $this->assertEqualsCanonicalizing(['post_liked', 'post_commented'], $author->notifications()->get()->pluck('data.kind')->all());
        $this->get('/feed')->assertInertia(fn ($page) => $page->where('counts.notifications', 2));
        $this->getJson('/notifications/latest')->assertJsonPath('notifications.0.actor.id', $fan->id);

        $id = $author->notifications()->first()->id;
        $this->get("/notifications/{$id}")->assertRedirect("/posts/{$post->id}");
        $this->post('/notifications/read');
        $this->assertSame(0, $author->unreadNotifications()->count());

        // Read notices leave the bell after a week but stay on the full page.
        $this->getJson('/notifications/latest')->assertJsonCount(2, 'notifications');
        $this->travel(8)->days();
        $this->getJson('/notifications/latest')->assertJsonCount(0, 'notifications');
        $this->get('/notifications')->assertInertia(fn ($page) => $page->has('notifications', 2));
    }

    public function test_reactions_by_several_people_come_in_as_one_notice_with_the_face_left()
    {
        $author = User::factory()->create();
        [$anna, $boris, $dana] = User::factory()->count(3)->create();
        $post = Post::create(['user_id' => $author->id, 'body' => 'Шалом']);

        $this->actingAs($anna)->post("/posts/{$post->id}/like", ['type' => 'haha']);
        $this->travel(1)->minutes();
        $this->actingAs($boris)->post("/posts/{$post->id}/like");
        $this->travel(1)->minutes();
        $this->actingAs($dana)->post("/posts/{$post->id}/like");
        $this->actingAs($dana)->post("/posts/{$post->id}/like", ['type' => 'love']);

        $this->actingAs($author)->getJson('/notifications/latest')
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.actor.id', $dana->id)
            ->assertJsonPath('notifications.0.reaction', 'love')
            ->assertJsonPath('notifications.0.others', 1)
            ->assertJsonPath('notifications.0.read', false);

        // Opening the row reads all three.
        $this->get('/notifications/'.$this->getJson('/notifications/latest')->json('notifications.0.id'));
        $this->assertSame(0, $author->unreadNotifications()->count());
        $this->getJson('/notifications/latest')->assertJsonPath('notifications.0.read', true);
    }

    public function test_friend_requests_notify_both_ways()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $this->actingAs($anna)->post("/people/{$boris->id}/friend");
        $this->assertSame('friend_request', $boris->notifications()->first()->data['kind']);

        $this->actingAs($boris)->post("/people/{$anna->id}/friend");
        $this->assertSame(0, $boris->unreadNotifications()->count());
        $this->assertSame('friend_accepted', $anna->notifications()->first()->data['kind']);
    }
}
