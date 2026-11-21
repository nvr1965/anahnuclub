<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complaints about posts and comments, and what the team did about each. The reported text is kept
 * (snapshot) with its author, so the record stays when the post itself is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            // A guest who complains (a rights holder, someone named in a post) leaves an address to answer.
            $table->string('email', 190)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('reportable_type', 20);
            $table->unsignedBigInteger('reportable_id');
            $table->unsignedBigInteger('post_id')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('snapshot')->nullable();
            $table->string('reason', 20);
            $table->text('details')->nullable();
            // open, removed (the content was taken down) or kept (nothing wrong found).
            $table->string('status', 10)->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['reportable_type', 'reportable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
