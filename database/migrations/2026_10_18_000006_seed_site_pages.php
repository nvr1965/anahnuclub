<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The disclaimer and the privacy policy in the four starting languages. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (json_decode(file_get_contents(database_path('data/pages.json')), true) as $page) {
            DB::table('pages')->insertOrIgnore([
                'slug' => $page['slug'],
                'content' => json_encode($page['content'], JSON_UNESCAPED_UNICODE),
                'in_footer' => true,
                'published' => true,
                'sort' => $page['sort'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', ['disclaimer', 'privacy'])->delete();
    }
};
