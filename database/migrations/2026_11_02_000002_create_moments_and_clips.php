<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moments: a photo, a short video or a few words that disappear after a day.
     * Clips: vertical videos of up to a minute and a half, kept in the videos table.
     */
    public function up(): void
    {
        Schema::create('moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // photo | video | text
            $table->string('path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->string('text', 500)->nullable();
            $table->string('text_position', 10)->default('bottom');
            $table->string('background', 20)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('audience', 10)->default('friends'); // friends | members | list
            $table->foreignId('friend_list_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('duration')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('moment_views', function (Blueprint $table) {
            $table->foreignId('moment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->nullable();
            $table->primary(['moment_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_clip')->default(false);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('duration')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->index(['is_clip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['is_clip', 'created_at']);
            $table->dropColumn(['is_clip', 'width', 'height', 'duration', 'views_count']);
        });
        Schema::dropIfExists('moment_views');
        Schema::dropIfExists('moments');
    }
};
