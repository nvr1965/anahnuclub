<?php

namespace Tests\Feature;

use App\Jobs\TranslateInterface;
use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use App\Translation\InterfaceStrings;
use App\Translation\Providers\DeepL;
use App\Translation\Providers\Gemini;
use App\Translation\Providers\LibreTranslate;
use App\Translation\Providers\Microsoft;
use App\Translation\Providers\MyMemory;
use App\Translation\Providers\Provider;
use App\Translation\Translator;
use App\Translation\TranslatorChain;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class TranslatorChainTest extends TestCase
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

    private function fake(string $id): Provider
    {
        return new class($id) extends Provider
        {
            public function __construct(private string $id) {}

            public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
            {
                return array_map(fn ($t) => "[{$this->id}:$targetLocale] $t", $texts);
            }

            public function name(): string
            {
                return $this->id;
            }
        };
    }

    private function chain(array $entries): TranslatorChain
    {
        return new TranslatorChain(array_map(fn ($e) => $e + ['on' => true, 'daily_chars' => 0], $entries));
    }

    public function test_languages_deepl_lacks_go_to_the_next_service()
    {
        $chain = $this->chain([['id' => 'deepl'], ['id' => 'gemini']])->with('deepl', new DeepL('key:fx'))->with('gemini', $this->fake('gemini'));

        $this->assertSame('deepl', $chain->translate('Hello', 'uk'));
        $this->assertSame('deepl', $chain->name());
        $this->assertSame('[gemini:uz] Hello', $chain->translate('Hello', 'uz'));
        $this->assertSame('gemini', $chain->name());
        $this->assertTrue($chain->supports('uz'));
    }

    public function test_a_language_missing_on_the_libretranslate_server_is_named_in_the_error()
    {
        Http::fake(['lt.test/languages' => Http::response([['code' => 'ru'], ['code' => 'he']])]);
        $chain = $this->chain([['id' => 'libretranslate']])->with('libretranslate', new LibreTranslate('https://lt.test'));

        try {
            $chain->translate('Hello', 'es');
            $this->fail('No translator should take es');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('libretranslate: does not know es', $e->getMessage());
        }
    }

    public function test_a_failing_or_used_up_service_hands_over_to_the_next()
    {
        $broken = new class extends Provider
        {
            public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
            {
                throw new \RuntimeException('down');
            }

            public function name(): string
            {
                return 'broken';
            }
        };
        $chain = $this->chain([['id' => 'mymemory', 'daily_chars' => 10], ['id' => 'deepl'], ['id' => 'claude']])
            ->with('mymemory', $this->fake('mymemory'))->with('deepl', $broken)->with('claude', $this->fake('claude'));

        $this->assertSame('[mymemory:ru] Hi', $chain->translate('Hi', 'ru'));
        $this->assertSame(2, $chain->usedToday('mymemory'));
        // Too long for what is left of the free amount, and DeepL is down: Claude takes it.
        $this->assertSame('[claude:ru] Hello there', $chain->translate('Hello there', 'ru'));
    }

    public function test_switched_off_services_are_skipped_and_none_left_is_an_error()
    {
        $chain = (new TranslatorChain([['id' => 'mymemory', 'on' => false, 'daily_chars' => 0]]))->with('mymemory', $this->fake('mymemory'));

        $this->expectException(\RuntimeException::class);
        $chain->translate('Hi', 'ru');
    }

    public function test_admins_order_switch_and_check_the_services()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/translators')->assertInertia(fn ($page) => $page->component('admin/translators')->has('translators', 7));

        $this->put('/admin/translators', ['translators' => [
            ['id' => 'mymemory', 'on' => true, 'daily_chars' => 4500],
            ['id' => 'claude', 'on' => false, 'daily_chars' => 0],
        ]])->assertSessionHasNoErrors();

        $described = TranslatorChain::fromSettings()->describe();
        $this->assertSame(['mymemory', 'claude'], array_slice(array_column($described, 'id'), 0, 2));
        $this->assertTrue($described[0]['ready']);
        // Services left out of the saved list come back switched off.
        $this->assertFalse(collect($described)->firstWhere('id', 'deepl')['on']);

        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'Доброе утро, друзья!']])]);
        $this->postJson('/admin/translators/mymemory/check', ['locale' => 'ru'])->assertJson(['ok' => true, 'text' => 'Доброе утро, друзья!']);
        $this->postJson('/admin/translators/deepl/check', ['locale' => 'ru'])->assertNotFound();
    }

    public function test_mymemory_cuts_long_texts_between_words()
    {
        $pieces = MyMemory::split(str_repeat('слово ', 200));

        $this->assertGreaterThan(1, count($pieces));
        foreach ($pieces as $piece) {
            $this->assertLessThanOrEqual(450, strlen($piece));
        }
    }

    public function test_a_language_nobody_can_translate_is_not_added()
    {
        $this->app->instance(Translator::class, $this->chain([['id' => 'deepl']])->with('deepl', new DeepL('key:fx')));
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

        $this->assertSame(['Salom', 'Rahmat'], (new Gemini('k', 'm'))->translateMany(['Hello', 'Thanks'], 'uz', 'en'));
    }

    public function test_gemini_tries_again_when_busy_but_not_when_its_quota_is_used()
    {
        Sleep::fake();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'The model is overloaded.']], 503)
            ->push(['candidates' => [['content' => ['parts' => [['text' => '["Salom"]']]]]]])
            ->push(['error' => ['message' => 'Quota exceeded']], 429),
        ]);
        $gemini = new Gemini('k', 'm');

        $this->assertSame(['Salom'], $gemini->translateMany(['Hello'], 'uz', 'en'));
        Sleep::assertSleptTimes(1);

        try {
            $gemini->translateMany(['Hello'], 'uz', 'en');
            $this->fail('a used-up quota is an error');
        } catch (RequestException $e) {
            $this->assertSame(429, $e->response->status());
        }
        Sleep::assertSleptTimes(1);
        Http::assertSentCount(3);
    }

    public function test_a_service_switched_on_without_its_key_is_named_in_the_error()
    {
        config(['services.google_translate.key' => null]);
        $chain = $this->chain([['id' => 'mymemory', 'daily_chars' => 1], ['id' => 'google']])->with('mymemory', $this->fake('mymemory'));

        try {
            $chain->translate('Hello', 'uz');
            $this->fail('nothing could translate');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mymemory: today\'s limit used', $e->getMessage());
            $this->assertStringContainsString('google: no key', $e->getMessage());
        }
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

    public function test_splitting_a_refused_batch_stops_at_the_deadline_and_keeps_the_rest_missing()
    {
        Language::create(['code' => 'uz', 'name' => 'Uzbek', 'native_name' => 'Oʻzbekcha', 'rtl' => false, 'enabled' => false, 'sort' => 9]);
        // A slow service that refuses every batch but answers single strings: split down to single strings,
        // one chunk of 40 took 79 requests and the worker killed the run as timed out.
        $slow = new class extends FakeTranslator
        {
            public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
            {
                $this->calls++;
                usleep(150_000);
                if (count($texts) > 1) {
                    throw new \RuntimeException('busy');
                }

                return ["[$targetLocale] $texts[0]"];
            }
        };
        $before = count(InterfaceStrings::missing('uz'));

        $left = InterfaceStrings::fill('uz', $slow, 1);

        $this->assertLessThan(15, $slow->calls);
        $this->assertGreaterThan(0, $left);
        $this->assertLessThan($before, $left);
        // Nothing was stored as English just because its turn did not come.
        $auto = collect(InterfaceStrings::rows('uz'))->where('source', 'auto');
        $this->assertSame($before - $left, $auto->count());
        $this->assertTrue($auto->every(fn ($row) => str_starts_with($row['text'], '[uz] ')));
    }

    public function test_a_batch_the_service_garbles_is_split_and_the_language_does_not_stick()
    {
        Language::create(['code' => 'fa', 'name' => 'Persian', 'native_name' => 'فارسی', 'rtl' => true, 'enabled' => false, 'auto_enable' => true, 'sort' => 9]);
        // Like an AI service that answers a long batch with a shorter array: only small batches get through.
        $this->app->instance(Translator::class, $this->chain([['id' => 'ai']])->with('ai', new class extends Provider
        {
            public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
            {
                if (count($texts) > 8) {
                    throw new \RuntimeException('unexpected answer');
                }

                return array_map(fn ($t) => "[fa] $t", $texts);
            }

            public function name(): string
            {
                return 'ai';
            }
        }));

        (new TranslateInterface('fa', true))->handle($this->app->make(Translator::class));
        TranslateInterface::resumeAll();

        $this->assertSame([], InterfaceStrings::missing('fa'));
        $this->assertTrue(Language::find('fa')->enabled);
        InterfaceStrings::forget('fa');
    }

    public function test_the_reason_a_translation_paused_shows_on_the_admin_page()
    {
        $this->app->instance(Translator::class, $this->chain([['id' => 'used-up']])->with('used-up', new class extends Provider
        {
            public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
            {
                throw new \RuntimeException('daily limit reached');
            }

            public function name(): string
            {
                return 'used-up';
            }
        }));
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/languages', ['code' => 'tg', 'name' => 'Tajik', 'native_name' => 'Тоҷикӣ'])->assertRedirect();

        $this->actingAs($admin)->get('/admin/languages')->assertInertia(fn ($page) => $page
            ->where('languages', fn ($rows) => str_contains((string) collect($rows)->firstWhere('code', 'tg')['paused'], 'daily limit reached')));
        InterfaceStrings::forget('tg');
    }

    public function test_microsoft_translates_a_batch()
    {
        Http::fake(['api.cognitive.microsofttranslator.com/*' => Http::response([['translations' => [['text' => 'Hallo', 'to' => 'de']]], ['translations' => [['text' => 'Welt', 'to' => 'de']]]])]);

        $this->assertSame(['Hallo', 'Welt'], (new Microsoft('key', 'westeurope'))->translateMany(['Hello', 'World'], 'de', 'en'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'to=de') && $r->header('Ocp-Apim-Subscription-Region')[0] === 'westeurope' && $r[0]['Text'] === 'Hello');
    }

    public function test_interface_translation_pauses_at_the_daily_limit_and_picks_up_again()
    {
        $this->app->instance(Translator::class, $this->chain([['id' => 'used-up']])->with('used-up', new class extends Provider
        {
            public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
            {
                throw new \RuntimeException('daily limit reached');
            }

            public function name(): string
            {
                return 'used-up';
            }
        }));
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/languages', ['code' => 'uk', 'name' => 'Ukrainian', 'native_name' => 'Українська'])->assertRedirect();
        $this->assertFalse(Language::find('uk')->enabled);
        $this->assertNotSame([], InterfaceStrings::missing('uk'));

        $this->app->instance(Translator::class, new FakeTranslator);
        TranslateInterface::resumeAll();

        $this->assertSame([], InterfaceStrings::missing('uk'));
        $this->assertTrue(Language::find('uk')->enabled);
        InterfaceStrings::forget('uk');
    }

    public function test_a_language_the_admin_turned_off_stays_off_after_the_hourly_translation()
    {
        $this->app->instance(Translator::class, new FakeTranslator);
        $admin = User::factory()->create(['is_admin' => true]);
        Language::create(['code' => 'hu', 'name' => 'Hungarian', 'native_name' => 'Magyar', 'rtl' => false, 'enabled' => true, 'sort' => 9]);
        $this->actingAs($admin)->patch('/admin/languages/hu', ['enabled' => false])->assertRedirect();
        $this->assertNotSame([], InterfaceStrings::missing('hu'));

        TranslateInterface::resumeAll();

        $this->assertSame([], InterfaceStrings::missing('hu'));
        $this->assertFalse(Language::find('hu')->enabled);
        InterfaceStrings::forget('hu');
    }

    public function test_the_deploy_seeder_keeps_the_order_and_switches_the_admin_set()
    {
        Language::whereKey('ru')->update(['sort' => 7, 'enabled' => false]);

        $this->seed(LanguageSeeder::class);

        $this->assertSame(7, (int) Language::find('ru')->sort);
        $this->assertFalse(Language::find('ru')->enabled);
    }

    public function test_admins_enter_keys_on_the_page_and_they_stay_encrypted()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config(['services.deepl.key' => null]);
        $this->assertFalse(collect(TranslatorChain::fromSettings()->describe())->firstWhere('id', 'deepl')['ready']);

        $this->actingAs($admin)->put('/admin/translators/deepl/keys', ['values' => ['services.deepl.key' => 'abcd-secret-1234:fx', 'services.anthropic.key' => 'not-this-one']])
            ->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('secret', (string) Setting::get('translator_keys'));
        $deepl = collect(TranslatorChain::fromSettings()->describe())->firstWhere('id', 'deepl');
        $this->assertTrue($deepl['ready']);
        $this->assertSame(['value' => '••••4:fx', 'source' => 'admin'], array_intersect_key($deepl['fields'][0], ['value' => 1, 'source' => 1]));
        $this->assertNotSame('not-this-one', config('services.anthropic.key'), 'a field of another service is ignored');
        $this->get('/admin/translators')->assertDontSee('abcd-secret');

        $this->put('/admin/translators/deepl/keys', ['values' => ['services.deepl.key' => '']]);
        $this->assertFalse(collect(TranslatorChain::fromSettings()->describe())->firstWhere('id', 'deepl')['ready']);
    }

    public function test_a_mymemory_email_entered_on_the_page_raises_its_limit()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config(['services.mymemory.email' => null]);
        $this->actingAs($admin)->put('/admin/translators', ['translators' => [['id' => 'mymemory', 'on' => true, 'daily_chars' => 4500]]]);

        $this->put('/admin/translators/mymemory/keys', ['values' => ['services.mymemory.email' => 'team@anahnu.club']]);

        $mymemory = collect(TranslatorChain::fromSettings()->describe())->firstWhere('id', 'mymemory');
        $this->assertSame(45000, $mymemory['daily_chars']);
        $this->assertSame('team@anahnu.club', $mymemory['fields'][0]['value']);
    }
}
