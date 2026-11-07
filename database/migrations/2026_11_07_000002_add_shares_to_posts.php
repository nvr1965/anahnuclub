<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sharing inside the club, as on Facebook: a post can carry another post (a repost to one's own feed,
 * to a group, or onto a friend's profile), and a post can be written on a friend's profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // The post shared; when it is deleted the share stays and says the post is gone.
            $table->foreignId('shared_post_id')->nullable()->index()->constrained('posts')->nullOnDelete();
            // The friend on whose profile the post was put.
            $table->foreignId('wall_user_id')->nullable()->index()->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_post_id');
            $table->dropConstrainedForeignId('wall_user_id');
        });
    }
};
