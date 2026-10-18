<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateContent;
use App\Models\Group;
use App\Models\Post;
use App\Support\Html;
use App\Translation\LanguageGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $post->delete();

        return back();
    }
}
