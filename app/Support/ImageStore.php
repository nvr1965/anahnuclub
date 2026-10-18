<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Saves uploaded pictures on the public disk, scaled down so pages stay light. */
class ImageStore
{
    /**
     * @param  int  $width  largest width kept
     * @param  int|null  $height  with a height the picture is cropped to fill width × height
     */
    public static function save(UploadedFile $file, string $folder, int $width, ?int $height = null): string
    {
        $path = $folder.'/'.Str::random(24).'.jpg';
        $source = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($file->getRealPath())) : false;

        if ($source === false) {
            return $file->storeAs($folder, Str::random(24).'.'.($file->extension() ?: 'jpg'), 'public');
        }

        $source = self::orient($source, $file);
        [$w, $h] = [imagesx($source), imagesy($source)];
        if ($height === null) {
            $scale = min(1, $width / $w);
            [$tw, $th, $sx, $sy, $sw, $sh] = [(int) round($w * $scale), (int) round($h * $scale), 0, 0, $w, $h];
        } else {
            $scale = max($width / $w, $height / $h);
            [$sw, $sh] = [(int) round($width / $scale), (int) round($height / $scale)];
            [$tw, $th, $sx, $sy] = [$width, $height, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2)];
        }

        $target = imagecreatetruecolor($tw, $th);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);

        ob_start();
        imagejpeg($target, null, 85);
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }

    /** Phone photos carry their rotation in EXIF instead of in the pixels. */
    private static function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($file->getRealPath()) : false;
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }

    public static function delete(?string $path): void
    {
        // Pictures brought over from the old site stay where they are.
        if ($path && ! str_starts_with($path, 'joomla/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
