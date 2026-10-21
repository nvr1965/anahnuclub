<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Html;
use App\Support\SiteMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Site pages anyone can read, such as the disclaimer and the privacy policy. */
class PageController extends Controller
{
    public function show(Request $request, Page $page): Response
    {
        abort_if(! $page->published && ! $request->user()?->is_admin, 404);
        $text = $page->in(app()->getLocale());

        $body = Html::clean($text['body']);

        return Inertia::render('pages/show', [
            'page' => [
                'slug' => $page->slug,
                'title' => $text['title'],
                'body' => $body,
                'locale' => $text['locale'],
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
        ])->withViewData(['meta' => SiteMeta::page($text['title'], SiteMeta::plain($body), [
            'noindex' => ! $page->published,
            'content' => ['heading' => $text['title'], 'html' => $body, 'links' => []],
        ])]);
    }
}
