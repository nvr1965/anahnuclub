<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a shared link looks like (title, picture, site), fetched once per address.
        Schema::create('link_previews', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048)->unique();
            $table->string('title', 300)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('image', 2048)->nullable();
            $table->string('site_name', 120)->nullable();
            $table->timestamps();
        });

        Schema::table('posts', function (Blueprint $table) {
            // The first link in the text, which the post shows as a card.
            $table->string('link_url', 2048)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('link_url'));
        Schema::dropIfExists('link_previews');
    }
};
