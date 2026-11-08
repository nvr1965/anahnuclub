<?php

use App\Support\PostTags;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Friends tagged in a post, as on Facebook ("with Anna and Boris", and @Anna in the text), and the
 * #hashtags of each post, so a hashtag opens every post that carries it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_user_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // "with": tagged beside the author's name; "mention": named with @ in the text.
            $table->string('kind', 10);
            $table->timestamps();
            $table->unique(['post_id', 'user_id', 'kind']);
            $table->index('user_id');
        });

        Schema::create('post_hashtags', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // In lower case, without the #: #Шаббат and #шаббат are one hashtag.
            $table->string('tag', 100);
            $table->timestamp('created_at')->nullable();
            $table->primary(['post_id', 'tag']);
            $table->index(['tag', 'post_id']);
        });

        // The posts written before carry hashtags too: they are found by them from now on.
        DB::table('posts')->select(['id', 'title', 'body', 'created_at'])
            ->where(fn ($q) => $q->where('body', 'like', '%#%')->orWhere('title', 'like', '%#%'))
            ->orderBy('id')
            ->chunkById(500, function ($posts) {
                $rows = [];
                foreach ($posts as $post) {
                    foreach (PostTags::hashtags(PostTags::plain($post->title.' '.$post->body)) as $tag) {
                        $rows[] = ['post_id' => $post->id, 'tag' => $tag, 'created_at' => $post->created_at];
                    }
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('post_hashtags')->insertOrIgnore($chunk);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_hashtags');
        Schema::dropIfExists('post_user_tags');
    }
};
