<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clears the authors' queue once (the curator asked): everything from sources that was not posted leaves it.
 * The rows stay hidden for two days so the same news is not taken again; then they are deleted.
 * What a curator added by hand and the posted news stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('feed_items')->whereIn('status', ['new', 'approved'])->whereNotNull('feed_source_id')
            ->update(['status' => 'skipped', 'reason' => 'stale']);
    }

    public function down(): void {}
};
