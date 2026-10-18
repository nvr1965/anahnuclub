<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Translation\DeepLTranslator;
use App\Translation\FallbackTranslator;
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
}
