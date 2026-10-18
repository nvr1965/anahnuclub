<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('locale', 12)->nullable();
            // youtube, vimeo or file (uploaded here)
            $table->string('provider', 16);
            $table->string('provider_id')->nullable();
            $table->string('url')->nullable();
            $table->string('path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->string('legacy_source', 32)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('video_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->index('user_id');
        });

        Schema::table('albums', function (Blueprint $table) {
            // timeline: pictures added to posts; avatar / cover: profile pictures.
            $table->string('kind', 16)->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('cover_path')->nullable();
            $table->string('locale', 12)->nullable();
        });

        Schema::create('event_user', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // going or interested
            $table->string('status', 16);
            $table->timestamps();
            $table->primary(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['cover_path', 'locale']));
        Schema::table('albums', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('photos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('post_id');
            $t->dropIndex(['user_id']);
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('video_id');
            $t->dropConstrainedForeignId('event_id');
        });
        Schema::dropIfExists('videos');
    }
};
