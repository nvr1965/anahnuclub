<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily filling of the feed by the club's authors (Фарик, Али, Витрих…): accounts run by the club,
 * each with its own country or topic, posting a short excerpt and a link from checked sources.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per club author: how much it posts and whether it is on.
        Schema::create('feed_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('topic')->nullable();
            $table->unsignedSmallInteger('daily_limit')->default(2);
            // Words that keep a news item out, one per line.
            $table->text('stop_words')->nullable();
            // Items of trusted sources go out without a curator's look.
            $table->boolean('auto_approve')->default(false);
            $table->boolean('paused')->default(false);
            $table->timestamps();
        });

        Schema::create('feed_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_channel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 1000);
            $table->string('locale', 8)->nullable();
            $table->boolean('trusted')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('fetched_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        // What the sources brought, and what became of it.
        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_source_id')->nullable()->constrained()->nullOnDelete();
            // The link without tracking parameters; one news item is taken once, ever.
            $table->string('url', 1000)->nullable();
            $table->string('url_hash', 40)->nullable()->unique();
            // The title reduced to its letters: the same news from two papers is taken once.
            $table->string('title_key', 120)->nullable()->index();
            $table->string('title', 500)->nullable();
            $table->text('summary')->nullable();
            $table->string('image', 1000)->nullable();
            $table->timestamp('published_at')->nullable();
            // new (waits for a curator), approved, posted, skipped.
            $table->string('status', 16)->default('new')->index();
            $table->string('reason')->nullable();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
        Schema::dropIfExists('feed_sources');
        Schema::dropIfExists('feed_channels');
    }
};
