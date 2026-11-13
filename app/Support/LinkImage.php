<?php

namespace App\Support;

use App\Models\LinkPreview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Copies a link card's picture to our own server. Shown from the other site, the picture made
 * visitors' browsers contact that site's picture server, and when an antivirus distrusts that
 * server (AVG and img.perxis.ru, Meduza's) it warned about a "threat" on our pages.
 * The same care as for reading pages: public addresses only, every redirect checked, a size cap.
 */
class LinkImage
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const MAX_REDIRECTS = 3;

    /** Wide enough for the big card; a small picture stays small, so it still shows as a thumbnail. */
    private const WIDTH = 1200;

    /** A picture that could not be copied is asked again only after this many minutes. */
    private const RETRY_MINUTES = 60;

    /** The card's picture copied to the public disk; its path, or null if it could not be had. */
    public static function store(LinkPreview $preview): ?string
    {
        if ($preview->image_path) {
            return $preview->image_path;
        }
        $failed = 'link-image-failed:'.$preview->id;
        if (! $preview->image || Cache::has($failed)) {
            return null;
        }
        $file = self::download($preview->image);
        if (! $file) {
            Cache::put($failed, true, now()->addMinutes(self::RETRY_MINUTES));

            return null;
        }
        try {
            $path = ImageStore::save(new UploadedFile($file, 'link.jpg', null, null, true), 'link-previews', self::WIDTH);
        } finally {
            @unlink($file);
        }
        $preview->forceFill(['image_path' => $path])->saveQuietly();

        return $path;
    }

    /** A temporary file with the picture, or null when the address is not a public picture. */
    private static function download(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! LinkPreviewFetcher::isPublic($url)) {
                return null;
            }
            try {
                $response = Http::timeout(8)->connectTimeout(4)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8'])
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->get($url);
            } catch (\Throwable) {
                return null;
            }
            if ($response->redirect()) {
                if (! $next = $response->header('Location')) {
                    return null;
                }
                $url = LinkPreviewFetcher::absolute($next, $url);

                continue;
            }
            // SVG can carry scripts, so only plain pictures.
            $type = strtolower($response->header('Content-Type'));
            if (! $response->successful() || ! str_starts_with($type, 'image/') || str_contains($type, 'svg')) {
                return null;
            }

            $body = $response->toPsrResponse()->getBody();
            $data = '';
            while (! $body->eof() && strlen($data) <= self::MAX_BYTES) {
                $data .= $body->read(65536);
            }
            $body->close();
            // Something the server calls a picture must also look like one.
            if (strlen($data) > self::MAX_BYTES || @getimagesizefromstring($data) === false) {
                return null;
            }
            $file = tempnam(sys_get_temp_dir(), 'link');
            file_put_contents($file, $data);

            return $file;
        }

        return null;
    }
}
