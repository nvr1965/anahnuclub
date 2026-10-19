<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messenger privacy, as on Facebook: a chat a non-friend starts waits in "Requests" until it is
     * accepted (or answered); a member may take messages from friends only; and a block that shuts
     * the messenger only, leaving profiles, posts and friendship as they are.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // everyone | friends
            $table->string('messages_from', 12)->default('everyone');
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            // When this member let the chat into their inbox; null means it is still a request.
            $table->timestamp('accepted_at')->nullable();
            // A request turned down; it comes back only with a newer message.
            $table->timestamp('declined_at')->nullable();
        });
        // Chats that already exist are ordinary chats.
        DB::table('conversation_user')->update(['accepted_at' => now()]);

        Schema::create('message_blocks', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'blocked_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_blocks');
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'declined_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('messages_from');
        });
    }
};
