<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\Page;
use App\Translation\InterfaceStrings;
use App\Translation\NullTranslator;
use App\Translation\Translator;
use Illuminate\Console\Command;

/**
 * Adds an interface language. Interface strings and site pages are machine-translated
 * when a translation provider is configured; the strings land in storage/app/lang/{code}.json.
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

        if ($translator instanceof NullTranslator) {
            $this->warn('No translation provider set, so new strings stay in English. Set TRANSLATION_DRIVER to translate them.');

            return self::SUCCESS;
        }

        $left = InterfaceStrings::fill($code, $translator);
        Page::all()->each(fn (Page $page) => $page->translateMissing($translator, [$code]));
        $this->info("Language $code added".($left ? ", $left strings still in English" : '').'.');

        return self::SUCCESS;
    }
}
