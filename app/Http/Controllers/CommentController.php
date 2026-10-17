<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateContent;
use App\Models\Post;
use App\Support\Html;
use App\Translation\LanguageGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => Html::fromPlainText($data['body']),
            'locale' => LanguageGuesser::guess($data['body']) ?? $request->user()->locale,
        ]);

        TranslateContent::dispatch($comment);

        return back();
    }
}
