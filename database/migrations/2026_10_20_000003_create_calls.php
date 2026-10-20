<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Video and audio calls between two members of a chat. Browsers talk to each other directly;
        // the site only passes their connection details back and forth (no live connection on shared hosting).
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('video')->default(true);
            // ringing, active, ended, declined, missed
            $table->string('status', 16)->default('ringing');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // Each side's page checks in while the call is on; one that goes quiet has left.
            $table->timestamp('caller_seen_at')->nullable();
            $table->timestamp('callee_seen_at')->nullable();
            $table->timestamps();
            $table->index(['callee_id', 'status']);
            $table->index(['caller_id', 'status']);
        });

        Schema::create('call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // offer, answer, candidate
            $table->string('type', 16);
            $table->text('payload');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_signals');
        Schema::dropIfExists('calls');
    }
};
