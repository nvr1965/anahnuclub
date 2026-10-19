<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Translation\DeepLTranslator;
use App\Translation\FallbackTranslator;
use App\Translation\GeminiTranslator;
use App\Translation\InterfaceStrings;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslatorFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('deepl.targets');
        Http::fake([
            '*/v2/languages*' => Http::response([['language' => 'UK', 'name' => 'Ukrainian'], ['language' => 'EN-GB', 'name' => 'English']]),
            '*/v2/translate' => Http::response(['translations' => [['text' => 'deepl']]]),
        ]);
    }

    public function test_languages_deepl_lacks_go_to_the_second_provider()
    {
        $translator = new FallbackTranslator(new DeepLTranslator('key:fx'), new FakeTranslator);

        $this->assertSame('deepl', $translator->translate('Hello', 'uk'));
        $this->assertSame('[uz] Hello', $translator->translate('Hello', 'uz'));
        $this->assertSame(['[uz] a', '[uz] b'], $translator->translateMany(['a', 'b'], 'uz'));
        $this->assertTrue($translator->supports('uz'));
    }

    public function test_a_language_nobody_can_translate_is_not_added()
    {
        $this->app->instance(Translator::class, new FallbackTranslator(new DeepLTranslator('key:fx')));
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/languages', ['code' => 'uz', 'name' => 'Uzbek', 'native_name' => 'Oʻzbekcha'])
            ->assertSessionHasErrors('code');
        $this->assertNull(Language::find('uz'));
    }

    protected function tearDown(): void
    {
        InterfaceStrings::forget('uz');
        InterfaceStrings::forget('ru');
        parent::tearDown();
    }

    public function test_gemini_translates_a_batch_as_a_json_array()
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '["Salom", "Rahmat"]']]]]],
        ])]);

        $this->assertSame(['Salom', 'Rahmat'], (new GeminiTranslator('k', 'm'))->translateMany(['Hello', 'Thanks'], 'uz', 'en'));
    }

    public function test_admins_correct_interface_texts_and_machine_translation_leaves_them_alone()
    {
        Language::create(['code' => 'uz', 'name' => 'Uzbek', 'native_name' => 'Oʻzbekcha', 'rtl' => false, 'enabled' => true, 'sort' => 9]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/languages/uz/texts', ['key' => 'Publish', 'text' => 'Eʼlon qilish'])->assertSessionHasNoErrors();
        // A text that drops a placeholder would show ":name" raw; it is refused.
        $this->put('/admin/languages/uz/texts', ['key' => 'Delete the language :name?', 'text' => 'Oʻchirilsinmi?'])->assertSessionHasErrors('text');
        $this->put('/admin/languages/uz/texts', ['key' => 'No such key', 'text' => 'x'])->assertNotFound();

        InterfaceStrings::fill('uz', new FakeTranslator);
        $strings = InterfaceStrings::read('uz');
        $this->assertSame('Eʼlon qilish', $strings['Publish']);
        $this->assertSame('[uz] Cancel', $strings['Cancel']);

        $this->get('/admin/languages/uz/texts')->assertInertia(fn ($page) => $page->component('admin/language-texts')
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('key', 'Publish')['source'] === 'manual'));

        // Built-in languages can be corrected too, without touching the code.
        $this->put('/admin/languages/ru/texts', ['key' => 'Publish', 'text' => 'Отправить'])->assertSessionHasNoErrors();
        $this->assertSame('Отправить', InterfaceStrings::read('ru')['Publish']);
        $this->put('/admin/languages/ru/texts', ['key' => 'Publish', 'text' => ''])->assertSessionHasNoErrors();
        $this->assertSame('Опубликовать', InterfaceStrings::read('ru')['Publish']);
    }

    public function test_a_translation_that_breaks_a_placeholder_keeps_the_english()
    {
        Language::create(['code' => 'uz', 'name' => 'Uzbek', 'native_name' => 'Oʻzbekcha', 'rtl' => false, 'enabled' => false, 'sort' => 9]);
        $broken = new class extends FakeTranslator
        {
            public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
            {
                return 'tarjima';
            }
        };

        InterfaceStrings::fill('uz', $broken);

        $this->assertSame('Delete the language :name?', InterfaceStrings::read('uz')['Delete the language :name?']);
        $this->assertSame('tarjima', InterfaceStrings::read('uz')['Cancel']);
    }
}
