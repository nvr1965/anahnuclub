<?php

namespace App\Http\Controllers;

use App\Models\LinkPreview;
use App\Support\LinkImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

/** A link card's picture, copied to our server the first time it is asked for. */
class LinkImageController extends Controller
{
    public function __invoke(LinkPreview $linkPreview): RedirectResponse
    {
        $path = LinkImage::store($linkPreview);
        abort_unless($path, 404);

        return redirect(Storage::disk('public')->url($path));
    }
}
