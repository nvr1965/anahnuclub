<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Every sign-in to the site, and every failed attempt: when, from which address and device. */
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // What was typed in the login box, for failed attempts that match no member.
            $table->string('login')->nullable();
            $table->string('method', 20);
            $table->boolean('success');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['user_id', 'created_at']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
