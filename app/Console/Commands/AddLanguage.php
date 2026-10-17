<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Translation\NullTranslator;
use App\Translation\Translator;
use Illuminate\Console\Command;

/**
 * Adds an interface language. Interface strings are machine-translated from English
 * when a translation provider is configured; review lang/{code}.json afterwards.
 */
class AddLanguage extends Command
{
    protected $signature = 'language:add
        {code : Language code, for example de or fr}
        {name : English name, for example German}
        {native : Name in the language itself, for example Deutsch}
        {--rtl : The language is written right to left}';

    protected $description = 'Add a new interface language';

    public function handle(Translator $translator): int
    {
        $code = strtolower($this->argument('code'));

        Language::updateOrCreate(['code' => $code], [
            'name' => $this->argument('name'),
            'native_name' => $this->argument('native'),
            'rtl' => $this->option('rtl'),
            'enabled' => true,
            'sort' => (Language::max('sort') ?? 0) + 1,
        ]);

        $path = lang_path($code.'.json');
        $english = json_decode(file_get_contents(lang_path('en.json')), true);
        $existing = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $missing = array_diff_key($english, $existing);

        if ($translator instanceof NullTranslator) {
            $this->warn('No translation provider set, so new strings stay in English. Set TRANSLATION_DRIVER to translate them.');
        }

        $bar = $this->output->createProgressBar(count($missing));
        foreach ($missing as $key => $text) {
            $existing[$key] = $translator->translate($text, $code, 'en');
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        $this->info("Language $code added. Interface strings are in lang/$code.json.");

        return self::SUCCESS;
    }
}
