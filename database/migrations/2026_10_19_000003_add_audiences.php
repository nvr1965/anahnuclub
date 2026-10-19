<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who sees what, as on Facebook. A post is public, for friends, for friends except some, for some
     * friends, or for its author only; the people named for the last two kinds are in post_audience.
     * Profile details (city, website, birthday, gender, friends list) each have their own audience.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // public | friends | except | specific | only_me
            $table->string('audience', 12)->default('public')->index();
        });

        Schema::create('post_audience', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // The audience the post box starts with.
            $table->string('default_audience', 12)->default('public');
            // {"birthdate": "friends", ...}; a missing field is public.
            $table->json('privacy')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_audience');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['default_audience', 'privacy']);
        });
    }
};
