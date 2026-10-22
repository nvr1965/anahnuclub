<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Support\Embed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_post_opens_as_a_page_other_sites_can_frame()
    {
        $post = Post::create(['user_id' => User::factory()->create(['name' => 'Дана'])->id, 'body' => '<p>Шаббат шалом</p>']);

        $this->get("/embed/posts/{$post->id}")
            ->assertOk()
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
            ->assertHeaderMissing('X-Frame-Options')
            ->assertSee('Дана')
            ->assertSee('Шаббат шалом')
            ->assertSee(route('posts.show', $post->id), false);
    }

    public function test_only_public_posts_can_be_embedded()
    {
        $author = User::factory()->create();
        $friends = Post::create(['user_id' => $author->id, 'body' => 'для друзей', 'audience' => 'friends']);
        $secret = Group::create(['name' => 'Secret', 'slug' => 'secret', 'is_private' => true]);
        $inGroup = Post::create(['user_id' => $author->id, 'group_id' => $secret->id, 'body' => 'в закрытой группе']);

        // Even the author, signed in, gets no embed of a post that is not for everyone.
        $this->actingAs($author)->get("/embed/posts/{$friends->id}")->assertNotFound()->assertDontSee('для друзей');
        $this->get("/embed/posts/{$inGroup->id}")->assertNotFound()->assertDontSee('в закрытой группе');
        $this->get('/embed/posts/999999')->assertNotFound();

        $this->get('/feed')->assertInertia(fn ($page) => $page->where('posts.data.0.embeddable', false));
    }

    public function test_embed_code_of_other_sites_becomes_the_post_address()
    {
        $fb = '<iframe src="https://www.facebook.com/plugins/post.php?href=https%3A%2F%2Fwww.facebook.com%2Fgerman.waisman%2Fposts%2Fpfbid02abc&show_text=true&width=500" width="500" height="669" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true"></iframe>';
        $this->assertSame("Смотрите:\nhttps://www.facebook.com/german.waisman/posts/pfbid02abc", Embed::unwrap("Смотрите:\n".$fb));

        $insta = '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/p/C8xYz12AbCd/?utm_source=ig_embed" data-instgrm-version="14"><div><a href="https://www.instagram.com/p/C8xYz12AbCd/">View</a></div></blockquote> <script async src="//www.instagram.com/embed.js"></script>';
        $this->assertStringStartsWith('https://www.instagram.com/p/C8xYz12AbCd/', Embed::unwrap($insta));

        $tweet = '<blockquote class="twitter-tweet"><p lang="en">Hi</p>&mdash; Someone (@someone) <a href="https://twitter.com/someone/status/1790000000000000000?ref_src=twsrc%5Etfw">May 1, 2024</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
        $this->assertSame('https://twitter.com/someone/status/1790000000000000000?ref_src=twsrc%5Etfw', Embed::unwrap($tweet));

        $telegram = '<script async src="https://telegram.org/js/telegram-widget.js?22" data-telegram-post="durov/123" data-width="100%"></script>';
        $this->assertSame('https://t.me/durov/123', Embed::unwrap($telegram));

        // Code of any other site is not touched, so it is shown as plain text, never run.
        $evil = '<iframe src="https://evil.example/x"></iframe><script>alert(1)</script>';
        $this->assertSame($evil, Embed::unwrap($evil));
    }

    public function test_links_of_known_sites_play_inside_the_post()
    {
        $this->assertSame('https://www.facebook.com/plugins/post.php?href='.rawurlencode('https://www.facebook.com/someone/posts/123').'&show_text=true&width=500', Embed::fromUrl('https://m.facebook.com/someone/posts/123')['src']);
        $this->assertStringStartsWith('https://www.facebook.com/plugins/video.php?', Embed::fromUrl('https://www.facebook.com/reel/987')['src']);
        $this->assertSame('https://t.me/durov/123?embed=1', Embed::fromUrl('https://t.me/durov/123')['src']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', Embed::fromUrl('https://youtu.be/dQw4w9WgXcQ')['src']);
        $this->assertStringStartsWith('https://platform.twitter.com/embed/Tweet.html?id=1790000000000000000', Embed::fromUrl('https://x.com/a/status/1790000000000000000')['src']);
        $this->assertNull(Embed::fromUrl('https://www.facebook.com/someone'));
        $this->assertNull(Embed::fromUrl('https://evil.example/posts/1'));
        $this->assertNull(Embed::fromUrl('javascript:alert(1)'));

        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['body' => '<iframe src="https://www.facebook.com/plugins/post.php?href=https%3A%2F%2Fwww.facebook.com%2Fsomeone%2Fposts%2F123&width=500"></iframe>']);
        $post = Post::firstOrFail();
        $this->assertSame('https://www.facebook.com/someone/posts/123', $post->link_url);

        $this->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.embed.provider', 'facebook')
            ->where('posts.data.0.link', null)
            ->where('posts.data.0.embeddable', true));
    }
}
