<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the community rules and brings the disclaimer and privacy policy up to date
 * (calls, country, translation services, team@anahnu.club). A page an admin has
 * already edited is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (json_decode(file_get_contents(database_path('data/pages.json')), true) as $page) {
            $row = DB::table('pages')->where('slug', $page['slug'])->first();
            $content = json_encode($page['content'], JSON_UNESCAPED_UNICODE);
            if (! $row) {
                DB::table('pages')->insert([
                    'slug' => $page['slug'], 'content' => $content, 'in_footer' => true, 'published' => true,
                    'sort' => $page['sort'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            } elseif ($row->updated_at === $row->created_at) {
                DB::table('pages')->where('slug', $page['slug'])->update([
                    'content' => $content, 'sort' => $page['sort'],
                    // Both stamps move together, so the page still reads as unedited by an admin.
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'rules')->delete();
    }
};
