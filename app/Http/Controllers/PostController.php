<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Jobs\TranslateContent;
use App\Models\Group;
use App\Models\Post;
use App\Support\Html;
use App\Translation\LanguageGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        if ($data['group_id'] ?? null) {
            abort_unless(Group::find($data['group_id'])->members()->whereKey($request->user()->id)->exists(), 403);
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'group_id' => $data['group_id'] ?? null,
            'type' => 'status',
            'body' => Html::fromPlainText($data['body']),
            'locale' => LanguageGuesser::guess($data['body']) ?? $request->user()->locale,
        ]);

        // Translated right after the response is sent, so readers get it within seconds.
        TranslateContent::dispatchAfterResponse($post);

        return back();
    }

    /** A single post, open to anyone with the link unless it sits in a private or hidden group. */
    public function show(Request $request, Post $post): Response
    {
        $group = $post->group;
        if ($group) {
            abort_if(! $group->published && ! $request->user()?->is_admin, 404);
            $member = $request->user() && $group->members()->whereKey($request->user()->id)->exists();
            abort_if($group->is_private && ! $member && ! $request->user()?->is_admin, 404);
        }

        $post = FeedController::postQuery($request)->whereKey($post->id)->firstOrFail();
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], ' ', $post->body)))));
        preg_match('~<img[^>]+src=["\']([^"\']+)~i', Html::clean($post->body), $image);

        return Inertia::render('posts/show', [
            'post' => PostResource::make($post),
            'sidebar' => CommunitySidebar::for($request->user()),
        ])->withViewData(['meta' => [
            'url' => route('posts.show', $post),
            'title' => $post->title ?: ($post->user?->name ?? 'anahnu.club'),
            'description' => mb_strimwidth($text, 0, 200, '…'),
            'image' => isset($image[1]) ? url($image[1]) : $post->user?->avatar,
        ]]);
    }

    /** Authors can rewrite what they typed; the new text is translated again. */
    public function update(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id && $post->type === 'status', 403);
        $body = $request->validate(['body' => ['required', 'string', 'max:20000']])['body'];

        $post->update(['body' => Html::fromPlainText($body), 'locale' => LanguageGuesser::guess($body) ?? $post->locale]);
        TranslateContent::dispatchAfterResponse($post);

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $post->delete();

        return back();
    }
}
