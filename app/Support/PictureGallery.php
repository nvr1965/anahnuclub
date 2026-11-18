<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Drawn pictures a member can pick for the profile picture or the cover: the club authors' own pictures
 * (resources/club-authors) and the extra set drawn for members (resources/picture-gallery).
 * Each one has an id like "club-aigul" or "drawn-cat"; small copies for the picker are made once, on the public disk.
 */
class PictureGallery
{
    public const KINDS = ['avatar', 'cover'];

    /** @return array<string, string> id => file, in the order the picker shows them */
    public static function all(string $kind): array
    {
        $sets = $kind === 'avatar'
            ? ['club' => resource_path('club-authors/*.png'), 'drawn' => resource_path('picture-gallery/avatars/*.png')]
            // Covers without a title come first; the club authors' covers carry the name of their group.
            : ['drawn' => resource_path('picture-gallery/covers/*.jpg'), 'club' => resource_path('club-authors/covers/*.jpg')];

        $pictures = [];
        foreach ($sets as $set => $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $pictures[$set.'-'.pathinfo($file, PATHINFO_FILENAME)] = $file;
            }
        }

        return $pictures;
    }

    public static function file(string $kind, string $id): ?string
    {
        return self::all($kind)[$id] ?? null;
    }

    /** @return list<array{id: string, thumb: string}> */
    public static function thumbs(string $kind): array
    {
        [$width, $height] = $kind === 'avatar' ? [160, 160] : [492, 187];
        $list = [];
        foreach (self::all($kind) as $id => $file) {
            $path = 'gallery/'.$kind.'/'.$id.'-'.substr(md5((string) filemtime($file).filesize($file)), 0, 8).'.jpg';
            if (! Storage::disk('public')->exists($path)) {
                self::shrink($file, $path, $width, $height);
            }
            $list[] = ['id' => $id, 'thumb' => asset('storage/'.$path)];
        }

        return $list;
    }

    private static function shrink(string $file, string $path, int $width, int $height): void
    {
        $source = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($file)) : false;
        if ($source === false) {
            Storage::disk('public')->put($path, (string) file_get_contents($file));

            return;
        }
        $target = imagecreatetruecolor($width, $height);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        imagejpeg($target, null, 84);
        Storage::disk('public')->put($path, (string) ob_get_clean());
    }
}
