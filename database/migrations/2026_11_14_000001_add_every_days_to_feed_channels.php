<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An author that posts less than once a day: its daily number is spread over this many days
 * (1 post a day and 3 days is one post in three days). 1 is every day, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->unsignedTinyInteger('every_days')->default(1)->after('daily_limit');
        });
    }

    public function down(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->dropColumn('every_days');
        });
    }
};
