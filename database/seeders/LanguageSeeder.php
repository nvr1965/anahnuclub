<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'rtl' => false, 'sort' => 1],
            ['code' => 'he', 'name' => 'Hebrew', 'native_name' => 'עברית', 'rtl' => true, 'sort' => 2],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'rtl' => false, 'sort' => 3],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'rtl' => true, 'sort' => 4],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
