<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each deploy used to queue every unread link again (hundreds of dead pages) in front of the
 * translations of new posts, so they waited for hours. Those queued reads are dropped: the next
 * "posts:link-previews" queues the pages never read, on their own slow queue, and the hourly
 * retry takes recent posts' failures.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs')) {
            DB::table('jobs')->where('queue', 'default')->where('payload', 'like', '%FetchLinkPreview%')->delete();
        }
    }

    public function down(): void {}
};
