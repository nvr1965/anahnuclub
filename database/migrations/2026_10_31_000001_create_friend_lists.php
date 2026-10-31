<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Members' own lists of friends, and the suggestions they removed from "People you may know". */
    public function up(): void
    {
        Schema::create('friend_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
        });

        Schema::create('friend_list_user', function (Blueprint $table) {
            $table->foreignId('friend_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['friend_list_id', 'user_id']);
        });

        Schema::create('friend_suggestion_dismissals', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dismissed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'dismissed_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friend_suggestion_dismissals');
        Schema::dropIfExists('friend_list_user');
        Schema::dropIfExists('friend_lists');
    }
};
