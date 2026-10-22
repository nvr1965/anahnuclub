<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Post;
use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A post as a small page for other sites to show in an <iframe>, like Facebook's "Embed".
 * Only what any guest may read: public posts, outside private or hidden groups.
 */
class EmbedController extends Controller
{
    public function post(Request $request, int $post): Response
    {
        // The site that embeds the post picks its language (?lang=he); the reader's browser otherwise.
        $lang = $request->query('lang');
        if (is_string($lang) && Language::enabled()->contains('code', $lang)) {
            app()->setLocale($lang);
        }
        $locale = app()->getLocale();

        $post = Post::query()
            ->with(['user', 'group', 'photos', 'video', 'linkPreview', 'contentTranslations' => fn ($q) => $q->where('locale', $locale)])
            ->withCount(['likes', 'comments'])
            ->visibleTo(null)
            ->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereHas('group', fn (Builder $g) => $g->where('published', true)->where('is_private', false)))
            ->whereKey($post)
            ->first();

        $response = $post
            ? response()->view('embed.post', [
                'post' => $post,
                'body' => Html::clean($post->cachedTranslation('body', $locale) ?? $post->body),
                'title' => $post->cachedTranslation('title', $locale) ?? $post->title,
                'official' => $post->isOfficial(),
                'rtl' => (bool) Language::enabled()->firstWhere('code', $locale)?->rtl,
                'url' => route('posts.show', $post->id).'?lang='.$locale,
            ])
            : response()->view('embed.missing', ['rtl' => (bool) Language::enabled()->firstWhere('code', $locale)?->rtl], 404);

        // Meant to be shown inside other sites, and never indexed on its own.
        return $response->header('Content-Security-Policy', 'frame-ancestors *')->header('X-Robots-Tag', 'noindex');
    }
}
