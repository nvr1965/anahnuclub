<?php

namespace App\Http\Controllers;

use App\Support\SiteMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Share on anahnu.club" from another site: the composer opens with the page's title and link, as Facebook's sharer does. */
class ShareController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:500'],
            'text' => ['nullable', 'string', 'max:5000'],
        ]);
        // Only web links: anything else (javascript:, data:) is dropped.
        $url = trim((string) ($data['url'] ?? ''));
        $url = preg_match('~^https?://[^\s<>"\']+$~i', $url) ? $url : '';
        $text = trim((string) ($data['text'] ?? '')) ?: trim((string) ($data['title'] ?? ''));

        return Inertia::render('share', [
            'body' => trim($text.($text !== '' && $url !== '' ? "\n\n" : '').$url),
            'url' => $url ?: null,
            'sidebar' => CommunitySidebar::for($request->user()),
        ])->withViewData(['meta' => SiteMeta::page(__('Share on anahnu.club'))]);
    }
}
