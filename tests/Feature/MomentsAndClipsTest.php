<?php

namespace Tests\Feature;

use App\Models\FriendList;
use App\Models\Message;
use App\Models\Moment;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MomentsAndClipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function befriend(User $a, User $b): void
    {
        DB::table('friendships')->insert(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted' => true]);
    }

    public function test_a_photo_moment_is_seen_by_friends_for_a_day_and_kept_three_more_days_for_its_author()
    {
        [$author, $friend, $stranger] = User::factory()->count(3)->create();
        $this->befriend($author, $friend);

        $this->actingAs($author)->post('/moments', ['kind' => 'photo', 'photo' => UploadedFile::fake()->image('a.jpg', 1080, 1920), 'text' => 'Привет из Хайфы'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $moment = Moment::firstOrFail();
        Storage::disk('public')->assertExists($moment->path);
        $this->assertSame('friends', $moment->audience);

        // The friend sees a ring with something new; the stranger sees nothing.
        $this->actingAs($friend)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('moments.people.0.user.id', $author->id)->where('moments.people.0.unseen', true));
        $this->actingAs($stranger)->get('/feed')->assertInertia(fn ($page) => $page->where('moments.people', []));
        $this->actingAs($stranger)->getJson("/moments/people/{$author->id}")->assertJsonPath('moments', []);

        $this->actingAs($friend)->getJson("/moments/people/{$author->id}")->assertJsonPath('moments.0.text', 'Привет из Хайфы')->assertJsonPath('can_reply', true);
        $this->actingAs($friend)->post("/moments/{$moment->id}/view")->assertNoContent();
        $this->actingAs($friend)->get('/feed')->assertInertia(fn ($page) => $page->where('moments.people.0.unseen', false));
        $this->actingAs($author)->getJson("/moments/{$moment->id}/viewers")->assertJsonPath('viewers.0.id', $friend->id);
        $this->actingAs($friend)->getJson("/moments/{$moment->id}/viewers")->assertForbidden();

        // After a day it is gone for others, but stays in the author's archive.
        $this->travel(25)->hours();
        $this->actingAs($friend)->getJson("/moments/people/{$author->id}")->assertJsonPath('moments', []);
        $this->actingAs($author)->get('/moments/archive')->assertInertia(fn ($page) => $page->where('moments.0.active', false));

        // Three days later the scheduler deletes it with its file.
        $this->travel(3)->days();
        Artisan::call('model:prune', ['--model' => [Moment::class]]);
        $this->assertDatabaseCount('moments', 0);
        Storage::disk('public')->assertMissing($moment->path);
    }

    public function test_a_moment_for_a_friend_list_is_seen_only_by_that_list()
    {
        [$author, $close, $other] = User::factory()->count(3)->create();
        $this->befriend($author, $close);
        $this->befriend($author, $other);
        $list = FriendList::create(['user_id' => $author->id, 'name' => 'Близкие']);
        $list->members()->attach($close->id);

        $this->actingAs($author)->post('/moments', ['kind' => 'text', 'text' => 'Только своим', 'audience' => 'list', 'friend_list_id' => $list->id])->assertSessionHasNoErrors();

        $this->actingAs($close)->getJson("/moments/people/{$author->id}")->assertJsonCount(1, 'moments');
        $this->actingAs($other)->getJson("/moments/people/{$author->id}")->assertJsonCount(0, 'moments');

        // Someone else's list cannot be picked.
        $foreign = FriendList::create(['user_id' => $other->id, 'name' => 'x']);
        $this->actingAs($author)->post('/moments', ['kind' => 'text', 'text' => 'x', 'audience' => 'list', 'friend_list_id' => $foreign->id])->assertSessionHasErrors('friend_list_id');
    }

    public function test_a_reply_to_a_moment_goes_to_the_authors_messages_with_its_picture()
    {
        [$author, $friend] = User::factory()->count(2)->create();
        $this->befriend($author, $friend);
        $this->actingAs($author)->post('/moments', ['kind' => 'photo', 'photo' => UploadedFile::fake()->image('a.jpg', 600, 1000)]);
        $moment = Moment::firstOrFail();

        $this->actingAs($friend)->postJson("/moments/{$moment->id}/reply", ['body' => 'Красиво!'])->assertOk();

        $message = Message::firstOrFail();
        $this->assertStringContainsString('Красиво!', $message->body);
        $this->assertSame(1, $message->attachments()->count());
        $this->assertTrue($message->conversation->users()->whereKey($author->id)->exists());

        // Gone moment: no answer.
        $this->travel(2)->days();
        $this->actingAs($friend)->postJson("/moments/{$moment->id}/reply", ['body' => 'Ещё'])->assertNotFound();
    }

    public function test_the_author_can_share_an_archived_moment_again_and_delete_it()
    {
        $author = User::factory()->create();
        $this->actingAs($author)->post('/moments', ['kind' => 'text', 'text' => 'Снова', 'background' => 'ocean']);
        $old = Moment::firstOrFail();
        $this->travel(30)->hours();

        $this->actingAs($author)->post("/moments/{$old->id}/repost")->assertRedirect('/feed');
        $this->assertSame(1, Moment::active()->count());

        $this->actingAs(User::factory()->create())->delete("/moments/{$old->id}")->assertForbidden();
        $this->actingAs($author)->delete("/moments/{$old->id}")->assertRedirect();
        $this->assertDatabaseMissing('moments', ['id' => $old->id]);
    }

    public function test_a_clip_is_posted_to_the_feed_and_listed_in_clips_for_members_only()
    {
        [$author, $reader] = User::factory()->count(2)->create();

        $this->actingAs($author)->post('/posts', [
            'body' => 'Мой первый клип',
            'video' => UploadedFile::fake()->create('clip.mp4', 3000, 'video/mp4'),
            'clip' => true,
            'video_width' => 720,
            'video_height' => 1280,
            'video_duration' => 42.5,
        ])->assertSessionHasNoErrors();

        $video = Video::firstOrFail();
        $this->assertTrue($video->is_clip);
        $this->assertSame(43, $video->duration);
        $post = Post::firstOrFail();

        $this->actingAs($reader)->get('/clips')->assertOk()->assertInertia(fn ($page) => $page
            ->component('clips/index')->where('clips.0.id', $post->id)->where('clips.0.video.is_clip', true));
        $this->actingAs($reader)->get("/clips/{$post->id}")->assertInertia(fn ($page) => $page->where('clips.0.id', $post->id));
        $this->actingAs($reader)->post("/clips/{$post->id}/view")->assertNoContent();
        $this->actingAs($reader)->post("/clips/{$post->id}/view")->assertNoContent();
        $this->assertSame(1, $video->fresh()->views_count);

        // An ordinary video is not a clip.
        $this->actingAs($author)->post('/posts', ['video' => UploadedFile::fake()->create('v.mp4', 100, 'video/mp4')]);
        $this->actingAs($reader)->getJson('/clips/more?before='.(Post::max('id') + 1))->assertJsonCount(1, 'clips');

        // Guests are sent to log in.
        auth()->logout();
        $this->get('/clips')->assertRedirect('/login');
    }

    public function test_a_clip_longer_than_a_minute_and_a_half_is_refused()
    {
        $this->actingAs(User::factory()->create())->post('/posts', [
            'video' => UploadedFile::fake()->create('clip.mp4', 3000, 'video/mp4'),
            'clip' => true,
            'video_duration' => 200,
        ])->assertSessionHasErrors('video_duration');
    }
}
