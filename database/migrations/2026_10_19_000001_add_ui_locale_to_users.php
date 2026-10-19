<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The interface language a member picked by hand. Until they pick one the site follows the browser;
     * users.locale stays the language they write in (all members from the old site have "ru" there).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ui_locale', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_locale');
        });
    }
};
