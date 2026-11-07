<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The group of a club author's topic ("Новости России", "Интересно"…): its posts go there. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feed_channels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
