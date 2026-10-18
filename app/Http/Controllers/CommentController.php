<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateContent;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ClubActivity;
use App\Support\Html;
use App\Translation\LanguageGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        // No talking on the posts of someone you blocked or who blocked you.
        abort_if($post->user && $request->user()->isBlockedWith($post->user), 403);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => Html::fromPlainText($data['body']),
            'locale' => LanguageGuesser::guess($data['body']) ?? $request->user()->locale,
        ]);

        TranslateContent::dispatchAfterResponse($comment);

        // The author hears about every comment; others who commented hear that the talk goes on.
        $me = $request->user();
        $url = route('posts.show', $post);
        $subject = 'post:'.$post->id;
        ClubActivity::send($post->user, 'post_commented', $me, $url, $subject, $data['body']);
        User::query()
            ->whereIn('id', $post->comments()->whereNotNull('user_id')->select('user_id'))
            ->whereKeyNot([$me->id, $post->user_id ?? 0])
            ->get()
            ->each(fn (User $other) => ClubActivity::send($other, 'comment_also', $me, $url, $subject, $data['body']));

        return back();
    }
}
