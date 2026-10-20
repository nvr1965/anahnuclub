<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            // Which reaction, as on Facebook: like, love, care, haha, wow, sad or angry.
            $table->string('type', 16)->default('like')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('likes', fn (Blueprint $table) => $table->dropColumn('type'));
    }
};
