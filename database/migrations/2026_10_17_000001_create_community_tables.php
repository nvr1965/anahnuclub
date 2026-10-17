<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->string('code', 12)->primary();
            $table->string('name');
            $table->string('native_name');
            $table->boolean('rtl')->default(false);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('locale', 12)->default('ru')->after('email');
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->text('status')->nullable();
            $table->boolean('blocked')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->timestamp('last_seen_at')->nullable();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->boolean('approved')->default(true);
            $table->timestamps();
            $table->primary(['group_id', 'user_id']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('status');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('locale', 12)->nullable();
            $table->string('legacy_source', 32)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'created_at']);
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('locale', 12)->nullable();
            $table->string('legacy_source', 32)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('likeable');
            $table->timestamps();
            $table->unique(['user_id', 'likeable_type', 'likeable_id']);
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('friendships', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('accepted')->default(false);
            $table->timestamps();
            $table->primary(['user_id', 'friend_id']);
        });

        // Machine translations of user content, one row per field and target language.
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('field', 32);
            $table->string('locale', 12);
            $table->text('text');
            $table->string('provider', 32);
            $table->string('source_hash', 64);
            $table->timestamps();
            $table->unique(['translatable_type', 'translatable_id', 'field', 'locale'], 'content_translations_unique');
        });
    }

    public function down(): void
    {
        foreach (['content_translations', 'friendships', 'events', 'photos', 'albums', 'likes', 'comments', 'posts', 'group_user', 'groups', 'languages'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'locale', 'avatar_path', 'cover_path', 'status', 'blocked', 'is_admin', 'last_seen_at']);
        });
    }
};
