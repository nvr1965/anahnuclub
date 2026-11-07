<?php

use App\Support\Badges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member's level in the club (counted from what they post and the reactions they get) and the
     * special badges an administrator gives by hand, such as "Club author" or "Beta tester".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(0)->index();
            $table->json('badges')->nullable();
        });

        // Members who already post get their level at once, without notices about it.
        Badges::refreshAll(notify: false);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['level']);
            $table->dropColumn(['level', 'badges']);
        });
    }
};
