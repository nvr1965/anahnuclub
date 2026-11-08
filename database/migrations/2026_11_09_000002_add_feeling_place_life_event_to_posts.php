<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Add to your post", as on Facebook: how the author feels ("feeling happy"), where they are
 * ("at Nof HaGalil") and a life event (a new job, a wedding, aliyah…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('feeling', 30)->nullable();
            $table->string('place', 150)->nullable();
            $table->string('life_event', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['feeling', 'place', 'life_event']);
        });
    }
};
