<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pinned posts, each in its own place: at the top of everyone's feed (the main administrator),
 * at the top of the author's profile, at the top of a group (its owner and admins).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable()->index();
            $table->timestamp('profile_pinned_at')->nullable();
            $table->timestamp('group_pinned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['pinned_at']);
            $table->dropColumn(['pinned_at', 'profile_pinned_at', 'group_pinned_at']);
        });
    }
};
