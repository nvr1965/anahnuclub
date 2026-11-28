<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Link cards' pictures are only linked now, never copied: the copies made so far (all in
 * storage/app/public/link-previews, used by nothing else) are deleted, and so is the column that named them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Storage::disk('public')->deleteDirectory('link-previews');
        if (Schema::hasColumn('link_previews', 'image_path')) {
            Schema::table('link_previews', fn (Blueprint $table) => $table->dropColumn('image_path'));
        }
    }

    public function down(): void
    {
        Schema::table('link_previews', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable()->after('image');
        });
    }
};
