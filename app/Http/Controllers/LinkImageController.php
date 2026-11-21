<?php

namespace App\Http\Controllers;

use App\Models\LinkPreview;
use Illuminate\Http\RedirectResponse;

/**
 * The old address of a link card's picture (pages and caches made before pictures were only linked):
 * it now sends the browser to the picture on its own site; nothing is copied to our server.
 */
class LinkImageController extends Controller
{
    public function __invoke(LinkPreview $linkPreview): RedirectResponse
    {
        abort_unless($url = $linkPreview->imageUrl(), 404);

        return redirect()->away($url);
    }
}
