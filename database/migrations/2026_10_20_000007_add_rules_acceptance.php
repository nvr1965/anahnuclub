<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When each member accepted the community rules (asked on the welcome page at first entry,
 * and again after the rules change), and the rules brought up to date: private messages,
 * files, calls, invitations, automatic translation, accepting the rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('rules_accepted_at')->nullable();
        });

        foreach (json_decode(file_get_contents(database_path('data/pages.json')), true) as $page) {
            $row = DB::table('pages')->where('slug', $page['slug'])->first();
            // A page an admin has edited is left alone.
            if ($page['slug'] === 'rules' && $row && $row->updated_at === $row->created_at) {
                DB::table('pages')->where('slug', 'rules')->update([
                    'content' => json_encode($page['content'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        Cache::forget('rules.published');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rules_accepted_at');
        });
    }
};
