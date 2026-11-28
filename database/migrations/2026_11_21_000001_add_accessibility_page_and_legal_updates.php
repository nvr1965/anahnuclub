<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the accessibility statement (הצהרת נגישות, IS 5568) to the footer, and brings the disclaimer
 * (club authors are automated accounts, how a rights holder asks to remove a material) and the
 * privacy policy (the sign-in journal, push notifications, all the translation services, who answers
 * for the data) up to date. A page an admin has already edited is left alone.
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
            } elseif ($row->updated_at === $row->created_at && $row->content !== $content) {
                DB::table('pages')->where('slug', $page['slug'])->update([
                    'content' => $content,
                    // Both stamps move together, so the page still reads as unedited by an admin.
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'accessibility')->delete();
    }
};
