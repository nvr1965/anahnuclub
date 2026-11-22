<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateContent;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ClubActivity;
use App\Support\Background;
use App\Support\Html;
use App\Translation\LanguageGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->isVisibleTo($request->user()), 404);
        abort_if($post->isOfficial(), 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        // A reply to a reply joins the same thread: replies go one level deep.
        $answered = isset($data['parent_id']) ? $post->comments()->findOrFail($data['parent_id']) : null;
        $parent = $answered?->parent_id ? $answered->parent : $answered;
        $repliedTo = $answered?->user;
        // No talking on the posts of someone you blocked or who blocked you.
        abort_if($post->user && $request->user()->isBlockedWith($post->user), 403);

        $comment = $post->comments()->create([
            'parent_id' => $parent?->id,
            'user_id' => $request->user()->id,
            'body' => Html::fromPlainText($data['body']),
            'locale' => LanguageGuesser::guess($data['body']) ?? $request->user()->locale,
        ]);

        Background::run(new TranslateContent($comment));

        // The author hears about every comment, the one answered about the reply;
        // others who commented hear that the talk goes on.
        $me = $request->user();
        $url = route('posts.show', $post);
        $subject = 'post:'.$post->id;
        ClubActivity::send($post->user, 'post_commented', $me, $url, $subject, $data['body']);
        if ($repliedTo && ! $repliedTo->is($post->user)) {
            ClubActivity::send($repliedTo, 'comment_replied', $me, $url, $subject, $data['body']);
        }
        User::query()
            ->whereIn('id', $post->comments()->whereNotNull('user_id')->select('user_id'))
            ->whereKeyNot([$me->id, $post->user_id ?? 0, $repliedTo->id ?? 0])
            ->get()
            ->each(fn (User $other) => ClubActivity::send($other, 'comment_also', $me, $url, $subject, $data['body']));

        return back();
    }

    /**
     * The author rewrites a comment, as on Facebook: readers see "edited", the new text is translated
     * again, and the words first written stay stored for the team (original_body).
     */
    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $me = $request->user();
        abort_unless($comment->canBeEditedBy($me), 403);
        abort_unless($comment->post?->isVisibleTo($me), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $body = Html::fromPlainText($data['body']);
        if ($body === $comment->body) {
            return back();
        }
        $comment->update([
            'original_body' => $comment->original_body ?? $comment->body,
            'body' => $body,
            'locale' => LanguageGuesser::guess($data['body']) ?? $comment->locale,
            'edited_at' => now(),
        ]);
        Background::run(new TranslateContent($comment));

        return back();
    }

    /** Its author, the post's author, the group's moderators or the team take a comment away, with its replies. */
    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $post = $comment->post;
        abort_unless($post && $comment->canBeDeletedBy($request->user(), $post), 403);
        $comment->delete();

        return back();
    }
}
