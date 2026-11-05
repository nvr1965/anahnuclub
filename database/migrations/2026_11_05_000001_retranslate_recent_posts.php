<?php

use App\Jobs\TranslateContent;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

/**
 * Posts and comments of the last few days whose translation ran out of the services' daily limits
 * get one more run. Languages already translated are read from storage and cost nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([Post::class, Comment::class] as $model) {
            $model::where('created_at', '>', now()->subDays(3))->latest('id')->limit(200)->get()
                ->each(fn ($item) => TranslateContent::dispatch($item));
        }
    }

    public function down(): void {}
};
