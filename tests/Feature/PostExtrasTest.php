<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Add to your post", as on Facebook: a feeling, a place, a life event, a GIF. */
class PostExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_can_carry_a_feeling_a_place_and_a_life_event_without_words()
    {
        $anna = User::factory()->create();

        $this->actingAs($anna)->post('/posts', [
            'feeling' => 'happy',
            'place' => "  Nof   HaGalil\n",
            'life_event' => 'aliyah',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertSame(['happy', 'Nof HaGalil', 'aliyah', 'status'], [$post->feeling, $post->place, $post->life_event, $post->type]);

        $this->actingAs($anna)->get("/posts/{$post->id}")->assertInertia(fn ($page) => $page
            ->where('post.data.feeling', 'happy')
            ->where('post.data.place', 'Nof HaGalil')
            ->where('post.data.life_event', 'aliyah'));

        // Only the faces and events the page knows.
        $this->actingAs($anna)->post('/posts', ['body' => 'x', 'feeling' => 'evil'])->assertSessionHasErrors('feeling');
        $this->actingAs($anna)->post('/posts', [])->assertSessionHasErrors('body');
    }

    public function test_a_gif_keeps_its_motion()
    {
        Storage::fake('public');
        $anna = User::factory()->create();
        // The smallest GIF there is: one transparent pixel.
        $gif = UploadedFile::fake()->createWithContent('fun.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $this->actingAs($anna)->post('/posts', ['images' => [$gif]])->assertRedirect();

        $photo = Post::firstOrFail()->photos()->firstOrFail();
        $this->assertStringEndsWith('.gif', $photo->path);
        Storage::disk('public')->assertExists($photo->path);
    }
}
