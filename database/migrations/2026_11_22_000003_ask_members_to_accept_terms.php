<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The terms of use are new, and with them the age of 16: every member confirms both, with the community rules,
 * on the next visit (the same page as after a change of the rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        // A new site has no members to ask.
        if (DB::table('users')->exists()) {
            Setting::put('rules_changed_at', now()->toIso8601String());
        }
    }

    public function down(): void
    {
        // Members who have accepted keep their acceptance.
    }
};
