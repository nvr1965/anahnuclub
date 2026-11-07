<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The authors post by themselves; a curator only adds or holds back, when it wants to. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->boolean('auto_approve')->default(true)->change();
        });
        DB::table('feed_channels')->update(['auto_approve' => true]);
        // What waited for a look so far goes to the authors' queues.
        DB::table('feed_items')->where('status', 'new')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->boolean('auto_approve')->default(false)->change();
        });
    }
};
