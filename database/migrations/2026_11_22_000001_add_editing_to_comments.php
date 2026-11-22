<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Authors rewrite their own comments, as on Facebook; readers see "edited".
            $table->timestamp('edited_at')->nullable()->after('locale');
            // The text as first written stays for the team: the club does not change what members write.
            $table->text('original_body')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'original_body']);
        });
    }
};
