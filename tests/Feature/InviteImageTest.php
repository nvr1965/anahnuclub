<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Support\InviteImage;
use App\Translation\InterfaceStrings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InviteImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    protected function tearDown(): void
    {
        InterfaceStrings::forget('kk');
        parent::tearDown();
    }

    public function test_every_language_the_site_starts_with_has_an_invitation_picture()
    {
        foreach (['ru', 'he', 'en', 'ar', 'de', 'uk', 'uz', 'hu', 'sr', 'fa', 'tg'] as $code) {
            $this->assertFileExists(public_path("images/invite/$code.jpg"));
        }
    }

    public function test_the_admin_page_hands_a_new_language_the_texts_to_draw_its_picture_from()
    {
        Language::create(['code' => 'kk', 'name' => 'Kazakh', 'native_name' => 'Қазақша', 'rtl' => false, 'enabled' => true, 'sort' => 9]);

        $this->actingAs($this->admin)->get('/admin/languages')->assertInertia(fn ($page) => $page
            ->where('languages', fn ($languages) => collect($languages)->firstWhere('code', 'kk')['invite']['has'] === false
                && collect($languages)->firstWhere('code', 'kk')['invite']['ready'] === false
                && collect($languages)->firstWhere('code', 'kk')['invite']['texts']['cta'] === 'Join the club'
                && collect($languages)->firstWhere('code', 'ru')['invite']['texts']['cta'] === 'Вступайте в клуб'
                && collect($languages)->firstWhere('code', 'ru')['invite']['ready'] === true));

        // Once the interface is translated the picture can be drawn.
        foreach (InviteImage::KEYS as $key) {
            InterfaceStrings::correct('kk', $key, "kk $key");
        }
        $this->assertTrue(InviteImage::texts('kk')['ready']);
        $this->assertSame('kk Join the club', InviteImage::texts('kk')['texts']['cta']);
    }

    public function test_a_drawn_picture_is_saved_and_shown_on_links_in_that_language()
    {
        Language::create(['code' => 'kk', 'name' => 'Kazakh', 'native_name' => 'Қазақша', 'rtl' => false, 'enabled' => true, 'sort' => 9]);

        // Before, links in it show the English picture.
        $this->get('/?lang=kk')->assertSee('images/invite/en.jpg', false);

        $this->actingAs($this->admin)->post('/admin/languages/kk/invite-image', ['image' => UploadedFile::fake()->image('x.jpg', 100, 100)])
            ->assertSessionHasErrors('image');
        $this->post('/admin/languages/kk/invite-image', ['image' => UploadedFile::fake()->image('kk.jpg', 1200, 630)])->assertRedirect();
        Storage::disk('public')->assertExists('invite/kk.jpg');

        auth()->logout();
        $this->get('/?lang=kk')->assertSee('storage/invite/kk.jpg?v=', false);

        $this->actingAs(User::factory()->create())->post('/admin/languages/kk/invite-image', ['image' => UploadedFile::fake()->image('kk.jpg', 1200, 630)])
            ->assertForbidden();

        // Deleting the language takes its picture along.
        $this->actingAs($this->admin)->delete('/admin/languages/kk');
        Storage::disk('public')->assertMissing('invite/kk.jpg');
    }

    public function test_a_picture_drawn_from_older_texts_is_drawn_again()
    {
        // The pictures the site comes with match the texts of its built-in languages.
        foreach (['ru', 'he', 'en', 'ar'] as $code) {
            $this->assertTrue(InviteImage::current($code), $code);
        }

        Language::create(['code' => 'kk', 'name' => 'Kazakh', 'native_name' => 'Қазақша', 'rtl' => false, 'enabled' => true, 'sort' => 9]);
        $this->actingAs($this->admin)->post('/admin/languages/kk/invite-image', ['image' => UploadedFile::fake()->image('kk.jpg', 1200, 630)]);
        $this->assertTrue(InviteImage::current('kk'));

        // A text on the picture changes: the admin page is told to draw it again.
        InterfaceStrings::correct('kk', 'Join the club', 'kk new');
        $this->assertFalse(InviteImage::current('kk'));
        $this->get('/admin/languages')->assertInertia(fn ($page) => $page
            ->where('languages', fn ($languages) => collect($languages)->firstWhere('code', 'kk')['invite']['has'] === false));

        // A drawing from older texts gives way to the site's own picture when that one is up to date.
        Storage::disk('public')->put('invite/ru.jpg', 'old');
        $this->assertFalse(InviteImage::current('ru'));
        $this->assertStringContainsString('images/invite/ru.jpg', InviteImage::url('ru'));

        // So does a picture drawn before pictures remembered their texts.
        $this->post('/admin/languages/kk/invite-image', ['image' => UploadedFile::fake()->image('kk.jpg', 1200, 630)]);
        Storage::disk('public')->delete('invite/kk.sig');
        $this->assertFalse(InviteImage::current('kk'));
    }
}
