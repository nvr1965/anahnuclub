<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Community rules: the advertising ban now names medicines instead of casinos and betting.
 * A rules page an admin has already edited is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (json_decode(file_get_contents(database_path('data/pages.json')), true) as $page) {
            $row = DB::table('pages')->where('slug', $page['slug'])->first();
            if ($page['slug'] === 'rules' && $row && $row->updated_at === $row->created_at) {
                DB::table('pages')->where('slug', 'rules')->update([
                    'content' => json_encode($page['content'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        Cache::forget('rules.published');
    }

    public function down(): void {}
};
