<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who the site's code belongs to: the author's name in English and Russian, the notice that is also at the top of
 * every source file, and the year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copyright', function (Blueprint $table) {
            $table->id();
            $table->string('owner_en');
            $table->string('owner_ru');
            $table->string('role');
            $table->text('notice');
            $table->unsignedSmallInteger('year');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('copyright')->insert([
            'owner_en' => 'Nikolay Reyzvikh',
            'owner_ru' => 'Николай Рейзвих',
            'role' => 'Author and Developer',
            'notice' => 'Author and Developer: Nikolay Reyzvikh (Николай Рейзвих). All rights reserved. Developed with the use of Claude.',
            'year' => 2026,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('copyright');
    }
};
