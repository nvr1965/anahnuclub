<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A language added in the admin waits switched off for its machine translation and switches on when it is done.
 * Before, "switched off" alone was taken for "waiting", so the hourly run switched back on languages
 * the admin had turned off. Now only a language with auto_enable switches on by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->boolean('auto_enable')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('auto_enable');
        });
    }
};
