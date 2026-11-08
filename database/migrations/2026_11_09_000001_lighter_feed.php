<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Less politics, more light reading: a source can give its items whole (jokes, recipes), and the jokes,
 * the cooking and the IT news get more posts a day. The news itself is cut by feeds.frequency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_sources', function (Blueprint $table) {
            $table->boolean('full_text')->default(false)->after('trusted');
        });
        foreach (['izya@authors.anahnu.club' => [2, 3], 'faina@authors.anahnu.club' => [1, 2], 'ron@authors.anahnu.club' => [2, 3]] as $email => [$min, $limit]) {
            $user = DB::table('users')->where('email', $email)->value('id');
            if ($user) {
                DB::table('feed_channels')->where('user_id', $user)->update(['daily_min' => $min, 'daily_limit' => $limit]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('feed_sources', function (Blueprint $table) {
            $table->dropColumn('full_text');
        });
    }
};
