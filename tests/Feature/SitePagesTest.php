<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Language;
use App\Models\Page;
use App\Models\User;
use App\Translation\InterfaceStrings;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        InterfaceStrings::forget('de');
        parent::tearDown();
    }

    public function test_the_footer_pages_are_there_in_every_starting_language()
    {
        foreach (['ru' => 'Отказ от ответственности', 'he' => 'הסרת אחריות', 'en' => 'Disclaimer', 'ar' => 'إخلاء المسؤولية'] as $locale => $title) {
            $this->withSession(['locale' => $locale])->get('/p/disclaimer')->assertOk()->assertInertia(fn ($page) => $page
                ->component('pages/show')
                ->where('page.title', $title)
                ->where('footer.pages.1.slug', 'privacy')
                ->where('footer.donate_url', 'https://www.paypal.com/donate/?hosted_button_id=9K3YLUVSY8K72')
            );
        }
    }

    public function test_anyone_can_send_feedback_and_admins_read_it()
    {
        $this->post('/contact', ['name' => 'Dana', 'email' => 'dana@example.com', 'subject' => 'Hi', 'body' => 'Great club!'])->assertRedirect();
        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'body' => 'Buy things', 'website' => 'spam.example'])->assertRedirect();
        $this->assertSame(['Great club!'], ContactMessage::pluck('body')->all());

        $member = User::factory()->create();
        $this->actingAs($member)->post('/contact', ['body' => 'Please delete my old posts'])->assertRedirect();
        $this->assertSame($member->email, ContactMessage::latest('id')->first()->email);

        $this->get('/admin/contact')->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/contact')->assertInertia(fn ($page) => $page->has('messages.data', 2));
    }

    public function test_admins_edit_pages_and_fill_missing_languages_by_translation()
    {
        $this->app->instance(Translator::class, new FakeTranslator);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/pages', ['slug' => 'rules', 'content' => ['ru' => ['title' => 'Правила', 'body' => '<p>Будьте вежливы</p>']]])
            ->assertRedirect('/admin/pages?page=rules');
        $this->post('/admin/pages/rules/translate', ['from' => 'ru'])->assertRedirect();

        $content = Page::where('slug', 'rules')->first()->content;
        $this->assertSame('[he] Правила', $content['he']['title']);
        $this->assertTrue($content['en']['auto']);

        $this->patch('/admin/site-settings', ['donate_url' => ''])->assertRedirect();
        $this->get('/p/rules')->assertInertia(fn ($page) => $page->where('footer.donate_url', ''));
    }

    public function test_a_new_language_gets_its_interface_translated_and_switches_on()
    {
        $this->app->instance(Translator::class, new FakeTranslator);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/languages', ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch'])->assertRedirect();

        $this->assertTrue(Language::find('de')->enabled);
        $this->assertSame([], InterfaceStrings::missing('de'));
        $this->assertSame('[de] :count members', InterfaceStrings::read('de')[':count members']);
        $this->assertStringStartsWith('[de] ', Page::where('slug', 'privacy')->first()->content['de']['title']);

        $this->delete('/admin/languages/de')->assertRedirect();
        $this->assertNull(Language::find('de'));
        $this->delete('/admin/languages/he')->assertStatus(422);
    }
}
