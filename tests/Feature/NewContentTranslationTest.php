<?php

namespace Tests\Feature;

use App\Jobs\TranslateContent;
use App\Models\FeedChannel;
use App\Models\Language;
use App\Models\Post;
use App\Models\User;
use App\Translation\ContentTranslator;
use App\Translation\TranslationTargets;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NewContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    private FakeTranslator $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeTranslator;
        $this->app->instance(Translator::class, $this->fake);
    }

    public function test_a_new_post_and_comment_are_translated_right_after_publishing()
    {
        $author = User::factory()->create(['locale' => 'ru']);
        $reader = User::factory()->create(['locale' => 'he']);

        $this->actingAs($author)->post('/posts', ['body' => 'Всем привет из Хайфы'])->assertRedirect();
        $post = Post::firstOrFail();
        $this->post("/posts/{$post->id}/comments", ['body' => 'Отличная новость'])->assertRedirect();

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation.fields.body', fn ($body) => str_contains($body, '[he]'))
            ->where('posts.data.0.comments.0.translation.fields.body', fn ($body) => str_contains($body, '[he] '))
        );
    }

    public function test_the_page_only_reads_stored_translations_and_leaves_old_posts_alone()
    {
        $reader = User::factory()->create(['locale' => 'en']);
        $fresh = Post::create(['user_id' => $reader->id, 'body' => 'Свежий пост']);
        $old = Post::create(['user_id' => $reader->id, 'body' => 'Старый пост']);
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.translation.pending', true)
            ->where('posts.data.1.translation', null));

        $this->postJson('/translations', ['items' => [['type' => 'post', 'id' => $fresh->id], ['type' => 'post', 'id' => $old->id]]])
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.fields', []);
        $this->assertSame(0, $this->fake->calls, 'the page never waits for the provider');
    }

    public function test_new_posts_go_ahead_of_time_only_into_languages_members_read_and_the_rest_when_opened()
    {
        Language::create(['code' => 'fa', 'name' => 'Persian', 'native_name' => 'فارسی', 'rtl' => true, 'enabled' => true, 'sort' => 9]);
        $author = User::factory()->create(['locale' => 'ru', 'ui_locale' => 'ru']);
        User::factory()->create(['locale' => 'he', 'ui_locale' => 'he']);

        $this->actingAs($author)->post('/posts', ['body' => 'Всем привет из Хайфы'])->assertRedirect();
        $post = Post::firstOrFail();

        $locales = $post->contentTranslations()->pluck('locale')->sort()->values()->all();
        $this->assertContains('he', $locales);
        $this->assertNotContains('fa', $locales, 'nobody reads Persian: not translated ahead of time');

        $this->withSession(['locale' => 'fa'])->postJson('/translations', ['items' => [['type' => 'post', 'id' => $post->id]]])
            ->assertOk();
        $this->assertTrue($post->contentTranslations()->where('locale', 'fa')->exists(), 'translated when opened in Persian');
    }

    public function test_only_members_seen_lately_and_no_news_author_make_a_language_go_ahead_of_time()
    {
        foreach (['uz' => "O'zbek", 'fa' => 'فارسی', 'ka' => 'ქართული'] as $code => $native) {
            Language::create(['code' => $code, 'name' => $code, 'native_name' => $native, 'rtl' => false, 'enabled' => true, 'sort' => 9]);
        }
        // Tried Uzbek once, months ago, and has not been back.
        $gone = User::factory()->create(['ui_locale' => 'uz']);
        $gone->forceFill(['created_at' => now()->subYear(), 'last_seen_at' => now()->subDays(TranslationTargets::ACTIVE_DAYS + 5)])->save();
        // A news channel's author account.
        FeedChannel::create(['user_id' => User::factory()->create(['locale' => 'fa'])->id]);
        // A member who reads Georgian and was here yesterday.
        $here = User::factory()->create(['locale' => 'ka']);
        $here->forceFill(['created_at' => now()->subYear(), 'last_seen_at' => now()->subDay()])->save();

        $eager = TranslationTargets::eager();

        $this->assertContains('ka', $eager);
        $this->assertNotContains('uz', $eager, 'a member gone for long does not count');
        $this->assertNotContains('fa', $eager, 'a news author is not a reader');
    }

    public function test_languages_no_service_could_take_are_tried_again_later()
    {
        $this->app->instance(Translator::class, new class implements Translator
        {
            public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
            {
                throw new RuntimeException('No translator could translate into '.$targetLocale);
            }

            public function name(): string
            {
                return 'down';
            }
        });
        User::factory()->create(['locale' => 'he']);
        $post = Post::create(['user_id' => User::factory()->create(['locale' => 'ru'])->id, 'body' => 'Всем привет из Хайфы', 'locale' => 'ru']);
        Queue::fake();

        (new TranslateContent($post))->handle(app(ContentTranslator::class));

        Queue::assertPushed(TranslateContent::class, fn ($job) => $job->model->is($post) && in_array('he', $job->locales, true) && $job->attempt === 1);
    }
}
