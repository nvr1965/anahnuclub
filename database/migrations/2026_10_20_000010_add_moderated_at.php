<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When an admin looked at a post or comment the word check flagged and kept it. */
    public function up(): void
    {
        foreach (['posts', 'comments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->timestamp('moderated_at')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['posts', 'comments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('moderated_at'));
        }
    }
};
