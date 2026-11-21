<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Club authors are automated accounts: their "was online" times, set when they posted, are cleared. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereIn('id', DB::table('feed_channels')->select('user_id'))->update(['last_seen_at' => null]);
    }

    public function down(): void {}
};
