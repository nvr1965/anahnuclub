<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_previews', function (Blueprint $table) {
            // Our own copy of the page's picture, so visitors' browsers never load it from the other site.
            $table->string('image_path', 255)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('link_previews', fn (Blueprint $table) => $table->dropColumn('image_path'));
    }
};
