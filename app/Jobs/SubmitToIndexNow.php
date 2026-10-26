<?php

namespace App\Jobs;

use App\Http\Controllers\SeoController;
use App\Models\Post;
use App\Support\SiteMeta;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells Yandex, Bing and the other IndexNow search engines about a new public post at once,
 * in each language it is in, instead of waiting for them to find it. Google does not take part;
 * it reads the sitemap. Runs after the post's translation, so the translated versions are known.
 */
class SubmitToIndexNow implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Post $post) {}

    public function handle(): void
    {
        $key = SiteMeta::indexNowKey();
        if (! SiteMeta::indexable() || ! $key || ! SeoController::publicPosts()->whereKey($this->post->id)->exists()) {
            return;
        }
        $url = route('posts.show', $this->post);
        $response = Http::timeout(15)->asJson()->post('https://api.indexnow.org/indexnow', [
            'host' => parse_url($url, PHP_URL_HOST),
            'key' => $key,
            'urlList' => array_values(SiteMeta::alternates($url, $this->post->languages())),
        ]);
        // 200 and 202 both mean taken. Anything else is only logged: the sitemap brings the post in anyway.
        if (! $response->successful()) {
            Log::warning('IndexNow did not take post '.$this->post->id.': '.$response->status().' '.mb_substr($response->body(), 0, 200));
        }
    }
}
