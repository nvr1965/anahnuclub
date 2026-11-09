<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Group;
use App\Models\LinkPreview;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Group names, link titles, captions and profile lines are shown in the reader's language. */
class LocalizedLabelsTest extends TestCase
{
    use RefreshDatabase;

    private FakeTranslator $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeTranslator;
        $this->app->instance(Translator::class, $this->fake);
    }

    public function test_group_names_follow_the_readers_language_everywhere()
    {
        $reader = User::factory()->create(['locale' => 'en']);
        $group = Group::create(['owner_id' => $reader->id, 'name' => 'Интересные новости', 'slug' => 'news', 'description' => '<p>Новости Израиля</p>', 'published' => true]);
        $group->members()->attach($reader->id, ['role' => 'owner', 'approved' => true]);
        Post::create(['user_id' => $reader->id, 'group_id' => $group->id, 'body' => 'Hello']);

        // The first page asks for the translation (here it is made at once; on the site, right after the page).
        $this->actingAs($reader)->get('/feed')->assertOk();
        $this->get('/feed')->assertInertia(fn ($page) => $page
            ->where('sidebar.groups.0.name', '[en] Интересные новости')
            ->where('posts.data.0.group.name', '[en] Интересные новости'));

        $this->get('/groups')->assertInertia(fn ($page) => $page->where('groups.data.0.name', '[en] Интересные новости'));

        $this->get('/groups/news')->assertInertia(fn ($page) => $page
            ->where('group.name', '[en] Интересные новости')
            ->where('group.description', fn ($d) => str_contains($d, '[en]'))
            // The settings form keeps the name as written.
            ->where('group.name_text', 'Интересные новости'));

        // Translated once per language and kept: the pages above asked the services for two texts only.
        $this->assertSame(2, $this->fake->calls);
    }

    public function test_nothing_is_translated_for_a_reader_of_the_same_language()
    {
        $reader = User::factory()->create(['locale' => 'ru']);
        $group = Group::create(['owner_id' => $reader->id, 'name' => 'Интересные новости', 'slug' => 'news', 'published' => true]);
        $group->members()->attach($reader->id, ['role' => 'owner', 'approved' => true]);

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('sidebar.groups.0.name', 'Интересные новости'));
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_a_failed_translation_shows_the_text_as_written_and_is_not_asked_again_at_once()
    {
        $this->app->instance(Translator::class, new class implements Translator
        {
            public int $calls = 0;

            public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
            {
                $this->calls++;
                throw new \RuntimeException('down');
            }

            public function name(): string
            {
                return 'down';
            }
        });
        $reader = User::factory()->create(['locale' => 'en']);
        $group = Group::create(['owner_id' => $reader->id, 'name' => 'Новости', 'slug' => 'news', 'published' => true]);
        $group->members()->attach($reader->id, ['role' => 'owner', 'approved' => true]);

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page->where('sidebar.groups.0.name', 'Новости'));
        $calls = app(Translator::class)->calls;
        $this->get('/feed')->assertOk();
        $this->assertSame($calls, app(Translator::class)->calls);
    }

    public function test_link_titles_and_photo_captions_are_translated()
    {
        $reader = User::factory()->create(['locale' => 'en']);
        $preview = LinkPreview::create(['url' => 'https://example.com/a', 'title' => 'Авария под Хайфой', 'description' => 'Двое пострадавших']);
        Post::create(['user_id' => $reader->id, 'body' => 'https://example.com/a']);

        $this->actingAs($reader)->get('/feed')->assertInertia(fn ($page) => $page
            ->where('posts.data.0.link.title', '[en] Авария под Хайфой')
            ->where('posts.data.0.link.description', '[en] Двое пострадавших'));

        $owner = User::factory()->create(['locale' => 'ru']);
        $album = Album::create(['user_id' => $owner->id, 'name' => 'Отпуск']);
        Photo::create(['album_id' => $album->id, 'user_id' => $owner->id, 'path' => 'p.jpg', 'caption' => 'Море в Эйлате']);

        $this->get("/albums/{$album->id}")->assertInertia(fn ($page) => $page
            ->where('album.name', '[en] Отпуск')
            ->where('photos.0.caption', '[en] Море в Эйлате'));
        // The owner edits them as written.
        $this->actingAs($owner)->get("/albums/{$album->id}")->assertInertia(fn ($page) => $page
            ->where('album.name', 'Отпуск')
            ->where('photos.0.caption', 'Море в Эйлате'));
    }

    public function test_profile_about_is_translated_for_others_but_not_for_its_owner()
    {
        $owner = User::factory()->create(['locale' => 'ru', 'about' => 'Живу в Хайфе', 'status' => 'На море']);
        $reader = User::factory()->create(['locale' => 'en']);

        $this->actingAs($reader)->get(route('people.show', $owner))->assertInertia(fn ($page) => $page
            ->where('profile.about', '[en] Живу в Хайфе')
            ->where('profile.status', '[en] На море'));
        $this->actingAs($owner)->get(route('people.show', $owner))->assertInertia(fn ($page) => $page
            ->where('profile.about', 'Живу в Хайфе'));
    }
}
