<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signing in with Google, Facebook and the like: one row per linked account.
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_id');
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
        });

        // The email now has to be confirmed. Members who already use the site keep their way in:
        // everyone who signed up here before, and old members who ever logged in on the Joomla site.
        // Joomla accounts never activated (most of them bots) stay unconfirmed.
        DB::table('users')->whereNull('email_verified_at')
            ->where(fn ($q) => $q->whereNotNull('last_seen_at')->orWhere('created_at', '>=', '2026-10-17'))
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
