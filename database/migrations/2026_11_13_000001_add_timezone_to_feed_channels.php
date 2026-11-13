<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A club author's own time zone ("Asia/Tashkent", "America/New_York"…): it posts and is online in its own
 * daytime. Empty means the club's (config feeds.timezone); feeds:setup fills it from database/seeders/feeds.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('topic');
        });
    }

    public function down(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
