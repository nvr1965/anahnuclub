<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Video and audio calls show in the chat as a line of their own ("Video call · 5 min",
 * "Missed call"), as in Messenger, and bring the chat to the top of the list like a message does.
 * Calls made before this are written into their chats too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('call_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        DB::table('calls')->whereIn('status', ['missed', 'declined', 'ended'])
            ->chunkById(500, function ($calls) {
                foreach ($calls as $call) {
                    $at = $call->ended_at ?? $call->updated_at ?? $call->created_at;
                    DB::table('messages')->insert([
                        'conversation_id' => $call->conversation_id,
                        'user_id' => $call->caller_id,
                        'body' => '',
                        'call_id' => $call->id,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);
                }
            });

        // Chats whose latest news is a call move up the list.
        $latest = DB::table('messages')->whereNotNull('call_id')->groupBy('conversation_id')
            ->selectRaw('conversation_id, max(created_at) as at')->get();
        foreach ($latest as $row) {
            DB::table('conversations')->where('id', $row->conversation_id)
                ->where(fn ($q) => $q->whereNull('last_message_at')->orWhere('last_message_at', '<', $row->at))
                ->update(['last_message_at' => $row->at]);
        }
    }

    public function down(): void
    {
        DB::table('messages')->whereNotNull('call_id')->delete();
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['call_id']);
            $table->dropUnique(['call_id']);
            $table->dropColumn('call_id');
        });
    }
};
